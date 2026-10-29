<?php
declare(strict_types=1);
// Offline tests use the same three additive intake types as the separate runtime;
// the canonical service/store/condition handler are never replaced.
spl_autoload_register(static function(string $class):void{
    $files=[
        'Appleklinika\\Inventory\\Application\\DeviceProduct\\DeviceIntakeSchema'=>'/opt/akbo-intake/DeviceIntakeSchema.php',
        'Appleklinika\\Inventory\\Application\\DeviceProduct\\DeviceOfferInput'=>'/opt/akbo-intake/DeviceOfferInput.php',
        'Appleklinika\\Inventory\\Domain\\ProductCondition\\DeviceFieldOptions'=>'/opt/akbo-intake-domain/DeviceFieldOptions.php',
    ];
    if(isset($files[$class])&&is_file($files[$class]))require_once $files[$class];
});
