<?php
/** Offline schema contract regression from the real migration CREATE statements. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__ . '/bootstrap-domain.php';
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\Schema;
use AppleKlinika\Buyback\Infrastructure\Persistence\WordPress\SchemaInspector;
define('ARRAY_A', 'ARRAY_A');
final class wpdb {
    public string $prefix = 'fixture_';
    public array $columns = [], $indexes = [];
    public function prepare($query, $value) { return str_replace('%s', $value, $query); }
    public function esc_like($value) { return $value; }
    public function get_var($query) {
        if (str_starts_with($query, 'SHOW TABLES LIKE ')) {
            $table = substr($query, 17);
            return isset($this->columns[$table]) ? $table : null;
        }
        return 0;
    }
    public function get_results($query, $format) {
        preg_match('/`([^`]+)`/', $query, $match);
        $columns = str_starts_with($query, 'SHOW COLUMNS');
        return array_map(static fn($value) => [$columns ? 'Field' : 'Key_name' => $value],
            ($columns ? $this->columns : $this->indexes)[$match[1]] ?? []);
    }
    public function load(string $migration): void {
        $text = file_get_contents(dirname(__DIR__) . '/src/Infrastructure/Persistence/WordPress/' . $migration . '.php');
        preg_match_all('/dbDelta\("CREATE TABLE \{\$(\w+)\} \((.*?)\) \{\$charsetCollate\};"\);/s', $text, $statements, PREG_SET_ORDER);
        $names = ['requests'=>Schema::REQUESTS, 'snapshots'=>Schema::SNAPSHOTS, 'events'=>Schema::EVENTS,
            'priceBooks'=>Schema::PRICE_BOOKS, 'priceRules'=>Schema::PRICE_RULES, 'table'=>Schema::REQUESTS];
        foreach ($statements as $statement) {
            $table = $this->prefix . $names[$statement[1]];
            preg_match_all('/^\s*([a-z_]+)\s+(?:bigint|varchar|char|int|datetime|longtext|text|tinyint)/m', $statement[2], $columns);
            preg_match_all('/^\s*(?:UNIQUE )?KEY\s+([a-z_]+)/m', $statement[2], $indexes);
            $this->columns[$table] = $columns[1];
            $this->indexes[$table] = array_merge(['PRIMARY'], $indexes[1]);
        }
    }
}
$n=0;
$check=static function(bool $ok,string $why) use (&$n):void { if(!$ok) throw new RuntimeException($why); $n++; };
$db=new wpdb(); $db->load('CoreSchemaMigration');
$inspector=new SchemaInspector($db,'1.0.0');
$inspector->assertTables([Schema::REQUESTS,Schema::SNAPSHOTS,Schema::EVENTS]);
$check(count($db->columns)===3,'Core fixture must represent three real tables.');
$check($inspector->tables()[Schema::REQUESTS]['missing_columns']===[],'Core must not require future contact columns.');
$check($inspector->tables()[Schema::REQUESTS]['missing_indexes']===[],'Core must not require future contact indexes.');
$check(in_array('customer_email',(new SchemaInspector($db,'1.2.0'))->tables()[Schema::REQUESTS]['missing_columns'],true),'Newer schema must still require contact fields.');
$db->load('PricingSchemaMigration');
(new SchemaInspector($db,'1.1.0'))->assertTables([Schema::PRICE_BOOKS,Schema::PRICE_RULES]);
$check(count($db->columns)===5,'Pricing fixture must add its real tables.');
$check((new SchemaInspector($db,'1.1.0'))->tables()[Schema::PRICE_RULES]['missing_columns']===[],'Pricing must not require future service component field.');
$check(in_array('affected_component_key',(new SchemaInspector($db,'1.5.0'))->tables()[Schema::PRICE_RULES]['missing_columns'],true),'Current runtime must retain strict component check.');
$db->load('PublicRequestSchemaMigration');
(new SchemaInspector($db,'1.2.0'))->assertTables([Schema::REQUESTS]);
$check((new SchemaInspector($db,'1.2.0'))->tables()[Schema::REQUESTS]['missing_columns']===[],'Public request migration remains valid.');
$db->columns[$db->prefix.Schema::REQUESTS]=array_diff($db->columns[$db->prefix.Schema::REQUESTS],['request_number']);
$check(in_array('request_number',(new SchemaInspector($db,'1.0.0'))->tables()[Schema::REQUESTS]['missing_columns'],true),'Historical requirements must not be weakened.');
$check(!(new SchemaInspector($db,'1.5.0'))->isHealthy(),'Missing current tables must still fail.');
echo "$n fresh-install migration schema assertions passed; offline fixture.\n";
