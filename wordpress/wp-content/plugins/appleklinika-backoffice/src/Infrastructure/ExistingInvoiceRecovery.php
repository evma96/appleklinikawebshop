<?php
declare(strict_types=1);
namespace Appleklinika\BackOffice\Infrastructure;

/** Operator-assisted recovery through the installed provider's PDF-read API; never issues an invoice. */
final class ExistingInvoiceRecovery
{
    public function recover(int $id, string $number, string $confirmedOrderNumber, string $reason): string
    {
        if (!current_user_can('manage_appleklinika_backoffice') && !current_user_can('manage_options')) {
            throw new \InvalidArgumentException('Nincs rendelés-egyeztetési jogosultság.');
        }
        if (!WooOrderReview::reviewEnvironment() || !preg_match('/^[\p{L}\p{N}._\/-]{1,100}$/uD', $number)
            || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 500) {
            throw new \InvalidArgumentException('Igazolt környezet, meglévő számlaszám és egyeztetési indok szükséges.');
        }
        return (new WooOrderMutex())->synchronized($id, function() use ($id, $number, $confirmedOrderNumber, $reason): string {
            $order = wc_get_order($id);
            if (!$order instanceof \WC_Order || !$order->is_paid() || !$order->get_date_paid()
                || !LifecycleConfiguration::manages($order) || (string)$order->get_order_number() !== $confirmedOrderNumber) {
                throw new \InvalidArgumentException('Igazoltan fizetett, kezelt Woo rendelés és a szolgáltatónál ellenőrzött rendelési hivatkozás szükséges.');
            }
            $existing = (string)$order->get_meta('_wc_szamlazz_invoice');
            if (($existing !== '' && $existing !== $number) || $order->get_meta('_wc_szamlazz_void') || $order->get_meta('_wc_szamlazz_receipt')) {
                throw new \InvalidArgumentException('Ellentmondó számla/nyugta/sztornó bizonyíték; automatikus felülírás tilos.');
            }
            if ($existing === $number && (new OrderDocuments())->filePath($order,'invoice') !== null) {
                $this->finish($order, $number, $confirmedOrderNumber, $reason);
                return 'already_recovered';
            }
            $attempt = $order->get_meta('_appleklinika_lifecycle_invoice');
            if (!in_array($attempt['state'] ?? '', ['generating','failed','uncertain','document_missing','ready'], true)) {
                throw new \InvalidArgumentException('Nincs megszakadt vagy hiányos számlaeredmény, amely helyreállítást igényel.');
            }
            if (!class_exists('WC_Szamlazz_Pro') || !\WC_Szamlazz_Pro::is_pro_enabled()) { throw new \RuntimeException('A számlázási licenc nem aktív.'); }
            $provider = \WC_Szamlazz(); $post = $_POST; $_POST = [];
            try {
                $key = (string)$provider->get_szamlazz_agent_key($order);
                if (!SzamlazzAutomation::verifiedAgentKey($key)) { throw new \RuntimeException('A számlázási fiók nincs igazolva.'); }
                // Same supported PDF lookup used by the vendor IPN, without its
                // mark-paid/status mutation. Order linkage is an explicit operator
                // attestation against the provider portal, not inferred from a filename.
                $xml = new \SimpleXMLElement('<xmlszamlapdf xmlns="http://www.szamlazz.hu/xmlszamlapdf"/>');
                $xml->addChild('szamlaagentkulcs', htmlspecialchars($key, ENT_XML1));
                $xml->addChild('szamlaszam', htmlspecialchars($number, ENT_XML1));
                $xml->addChild('valaszVerzio', '1');
                $response = $provider->xml_generator->generate($xml->asXML(), $id, 'action-szamla_agent_pdf');
                $body = is_array($response) ? ($response['agent_body'] ?? '') : '';
                if (!is_array($response) || !empty($response['error']) || !is_string($body) || !str_starts_with($body,'%PDF-')) {
                    throw new \RuntimeException('A meglévő PDF nem tölthető le; új számla nem készült.');
                }
                $file = $provider->xml_generator->save_pdf_file('invoice', $id, $body, $number);
                $order->update_meta_data('_wc_szamlazz_invoice', $number);
                $order->update_meta_data('_wc_szamlazz_invoice_pdf', $file);
                $order->save_meta_data();
                $fresh = wc_get_order($id);
                if ((new OrderDocuments())->filePath($fresh,'invoice') === null) { throw new \RuntimeException('A helyi PDF-mentést ellenőrizni kell; új számla nem készült.'); }
                $this->finish($fresh, $number, $confirmedOrderNumber, $reason);
                return 'recovered_existing_pdf';
            } catch (\Throwable) {
                (new WooOrderLifecycleStore())->issue($id, 'existing_invoice_recovery_incomplete');
                throw new \RuntimeException('A meglévő számla helyreállítása nem igazolt. Ellenőrizd a szolgáltatói és helyi dokumentumot; új számla nem készült.');
            } finally { $_POST = $post; }
        });
    }
    private function finish(\WC_Order $order, string $number, string $confirmedOrderNumber, string $reason): void
    {
        $repo = new WooOrderBackOfficeRepository();
        $record = $order->get_meta('_appleklinika_invoice_recovery');
        if (($record['state'] ?? '') === 'complete' && ($record['number'] ?? '') === $number) { return; }
        $order->update_meta_data('_appleklinika_invoice_recovery', ['actor'=>get_current_user_id(),'at'=>gmdate('c'),
            'number'=>$number,'confirmed_order'=>$confirmedOrderNumber,'reason'=>sanitize_text_field($reason),'state'=>'finishing']);
        $order->save_meta_data();
        if (!in_array('existing_invoice_recovered', array_column($repo->history($order), 'action'), true)) {
            $repo->recordDocumentAccess($order, 'existing_invoice_recovered');
        }
        (new \Appleklinika\BackOffice\Application\OrderLifecycle(new WooOrderLifecycleStore(),new WooOrderMutex(),new SzamlazzAutomation(),new WooCustomerMailer()))->paymentConfirmed($order->get_id());
        do_action('wc_szamlazz_document_created',['order_id'=>$order->get_id(),'document_type'=>'invoice']);
        $fresh = wc_get_order($order->get_id());
        $record = $fresh->get_meta('_appleklinika_invoice_recovery'); $record['state'] = 'complete';
        $fresh->update_meta_data('_appleklinika_invoice_recovery', $record); $fresh->save_meta_data();
    }

}
