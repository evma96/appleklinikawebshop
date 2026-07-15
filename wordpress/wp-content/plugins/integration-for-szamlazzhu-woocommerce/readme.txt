=== Számlázz.hu integráció WooCommerce-hez ===
Contributors: passatgt
Tags: szamlazz.hu, szamlazz, woocommerce, szamlazo, magyar
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 6.2.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Számlázz.hu összeköttetés WooCommerce-hez.

== Description ==

> **PRO verzió**
> A bővítménynek elérhető a PRO verziója évi 30 €-tól, amelyet itt vásárolhatsz meg: [https://visztpeter.me](https://visztpeter.me/woocommerce-szamlazz-hu/)
> A licensz kulcs egy weboldalon aktiválható, 1 évig érvényes és természetesen email-es support is jár hozzá beállításhoz, testreszabáshoz, konfiguráláshoz.
> A vásárlással támogathatod a fejlesztést akkor is, ha esetleg a PRO verzióban elérhető funkciókra nincs is szükséged.

= Funkciók =

* **Számlakészítés**
Minden rendelésnél egy kattintással tudsz számlát létrehozni:
 * A generált számlát eltárolja a rendelés adataiban.
 * A generált számlát letölti saját weboldalra is, egy véletlenszerű fájlnéven tárolja a wp-content/uploads/wc_szamlazz mappában
 * Fizetési határidő és megjegyzés írható a számlákhoz
 * Kuponokkal is működik, a számlán negatív tételként fog megjelenni
 * Szállítást is ráírja a számlára
 * A PDF fájl letölthető egyből a Rendelések táblázatból is, a műveletek, vagy a külön bekapcsolható Számlázz.hu oszlopból

* **Díjbekérő, előlegszámla, szállítólevél, sztornó számla**
A Számlázz.hu által támogatott összes dokumentumtípust létre tudod hozni a rendeléseidhez.

* **Csoportos műveletek**
A rendeléskezelőben a csportos műveletekkel tudsz számlát létrehozni és egyben letölteni, vagy nyomtatni a számlákat.

* **Adószám mező**
A számlázási adatok között megjelenjenik egy új adószám mező. Az adószámot a rendszer validálja, eltárolja, a vásárlónak küldött emailben és a rendelés adatai között is megjelenik.

* **Naplózás**
A rendelés jegyzeteiben látod, hogy pontosan mikor és milyen dokumentumot hoztál létre.

* **Automata fizetettnek jelölés**
Beállítható, melyik fizetési módoknál hozzon létre már fizetett számlát.

* **Nemzetközi számla**
Ha külföldre értékesítesz például euróban, lehetőség van a számla nyelv átállítására és az aktuális MNB árfolyam feltüntetésére a számlán. Kompatibilis WPML-el és Polylang-al is.

* **És még sok más**
Papír és elektronikus számla állítás, áfakulcs állítás, számlaszám előtag módosítása, letölthető számlák a vásárló profiljában, hibás számlakészítésről e-mailes értesítő stb...

= PRO verzióban elérhető =

* **Automata számlakészítés**
Lehetőség van számlát és egyéb dokumentumokat(díjbekérő, előlegszámla, szállítólevél, sztornó számla) automatikusan a rendelés állapota szerint létrehozni, különböző feltételek alapján.

* **Számlaértesítő**
Az ingyenes verzióban a számlázz.hu küldi ki a számlaértesítőt a vásárlónak. A PRO verzióban csatolni lehet a WooCommerce által küldött emailekhez, így nem fontos használni a Számlázz.hu számlaértesítőjét és a vásárlód egyel kevesebb emailt fog kapni.

* **Banki átutalások automata kezelése**
A Számlázz.hu-s Autokassza funkció segítségével a díjbekérő kifizetettségéről értesítést kaphat a webáruház, ami után automatán teljesítettnek jelölheti a számlát.

* **Speciális áfakulcsok használata**
Különböző feltételek alapján beállíthatod, hogy bizonyos termékek milyen speciális áfakulccsal jelenjenek meg a számlán(EUKT, EUT, EUFADE stb...).

* **Csoportos műveletek**
A dokumentumok létrehozása művelettel nem csak számlát, hanem díjbekérőt, előlegszámlát, szállítólevelet és sztornó számlát is készíthetsz.

* **Adatok felülírása**
Feltételekkel módosíthatod a számlán megjelenő előtagot és bankszámlaszám adatokat.

* **E-Nyugta**
Ha elektronikus termékeket, jegyeket, letölthető tartalmakat értékesítesz, nem fontos bekérni a vásárló számlázási címét, elég csak az email címét, a bővítmény pedig elektronikus nyugtát készít.

* **Cégadatok kitöltése automatán**
Az adószám mezőt a Számlázz.hu API-val validálja és a számlázási adatokat automatikusan kitölti.

* **EU adószám**
Az adószám mező elfogad EU adószámot is és automatikusan nettósítja a rendelést, ha sikerült validálni(a validáció a VIES API-n keresztül megy).

* **Könyvelési adatok**
Termékkategóriánként megadhatók a könyveléssel kapcsolatos adatok magyar és külföldi vásárlásoknál: főkönyvi szám, árbevételi főkönyvi szám, gazdasági esemény, áfa gazdasági esemény. A felhasználó azonosítóját pedig a vevő főkönyvi azonosítójaként eltárolja.

= Használat =
Részletes dokumentációt [itt](https://visztpeter.me/dokumentacio/) találsz.
Telepítés után a WooCommerce / Beállítások / Számlázz.hu oldalon meg kell adni az Agent kulcsod.
Minden rendelésnél jobb oldalon megjelenik egy új doboz, ahol egy gombnyomással létre lehet hozni a számlát. Az Opciók gombbal felül lehet írni a beállításokban megadott értékeket 1-1 számlához.
Ha az automata számlakészítés be van kapcsolva, akkor automatikusan létrehozza a számlát a rendszer a rendelés állapotának változásakor.
A számlakészítés kikapcsolható 1-1 rendelésnél az Opciók legördülőn belül.
Az elkészült számla a rendelés aloldalán és a rendelés listában az utolsó oszlopban található PDF ikonra kattintva letölthető.

**FONTOS:** Mindenen esetben ellenőrizd le, hogy a számlakészítés megfelelő e és konzultálj a könyvelőddel, neki is megfelelnek e a generált számlák. Sajnos minden esetet nem tudok tesztelni, különböző áfakulcsok, termékvariációk, kuponok stb..., így mindenképp teszteld le éles használat előtt és ha valami gond van, jelezd felém és megpróbálom javítani.

= Fejlesztőknek =

A plugin egy XML fájlt generál, ezt küldi el a szamlazz.hu-nak, majd az egy pdf-ben visszaküldi az elkészített számlát. Az XML fájl generálás előtt módosítható a `wc_szamlazz_xml` filterrel. Ez minden esetben az éppen aktív téma functions.php fájlban történjen, hogy az esetleges plugin frissítés ne törölje ki a módosításokat! Bővebb fejlesztői dokumentációt [itt](https://visztpeter.me/dokumentacio/) találsz.

== Installation ==

1. Töltsd le a bővítményt
2. Wordpress-ben bővítmények / új hozzáadása menüben fel kell tölteni
3. WooCommerce / Beállítások / Számlázz.hu menüpontban találhatod a beállítások, itt legalább az agent kulcs mezőt kötelező kitölteni
4. Működik

== Frequently Asked Questions ==

= Mi a különbség a PRO verzió és az ingyenes között? =

A PRO verzió sok hasznos funkciót tud, amiről [itt](https://visztpeter.me/woocommerce-szamlazz-hu/) olvashatsz. A legfontosabb az automata számlakészítés Továbbá 1 éves emailes support is jár hozzá.

= Hogyan lehet tesztelni a számlakészítést? =

A számlázz.hu fiókodban be tudod kapcsolni a [teszt üzemmódot.](https://tudastar.szamlazz.hu/gyik/szamlazz-hu-kipobalasa-elofizetes-nelkul)

= Teszt módban vagyok, de a számlaértesítő nem a vásárló email címére megy. =

A számlaértesítő teszt módban nem a vásárló email címére érkezik, hanem a számlázz.hu-n használt fiók email címére.

== Screenshots ==

1. Általános beállítások
2. Áfakulcsok és adózás beállítások
3. Számla tétel beállítások
4. Adószám mező beállítások
5. Automatizálás beállítások
6. Számlaértesítő beállítások
7. E-Nyugta beállítások
8. Számlakészítés doboz a rendelés oldalon

== Changelog ==

6.2.2
* EU adószám validálás fejlesztések
* IPN javítás HPOS esetén egyedi rendelésszámmal

6.2.1
* WP 7.0 kompatiblitás javítások
* Sztornózás oka megadható manuális sztornózásnál

6.2
* Adószám mező összes országhoz: ezzel a beállítással nem EU országok esetén is látszik a kötelezően kitöltendő adószám mező, csak nem validálja az értékét
* A wc_szamlazz_vat_number_validation_results filter hibás adószámnál is lefut és módosítható az eredmény

6.1.16
* Termék kategória és címke feltétel javítása(figyelembe veszi, ha több féle kategória és címke van a rendelésben)
* "Kizárólag" feltétel(pl csak akkor fut le, ha csak a kiválasztott(egy) kategória van a rendelésben)
* Komaptibilitás megjelölése legújabb WC verzióval

6.1.15
* Egyedi logó beállítás mező

6.1.14
* wc_szamlazz_deposit_negative_lines filter
* Kompatibilitás megjelölése legújabb WC verzióval
* EU adószám validálás javítás
* Termék címke feltétel

6.1.13
* 21% áfakulcs javítás

6.1.12
* HO áfakulcs
* Kompatibilitás megjelölése WP 6.9 és WC 10.3.6-tal
* EU adószám ellenőrzés javítás(ha rate limit hiba van, validnak kezeli)

6.1.11
* Javítás előző verzióhoz

6.1.10
* Kompatibilités 10.3 előtti WooCommerce verzióval

6.1.9
* Nyugta készítés javítás

6.1.8
* Kompatibilitás javítás legújabb Woo verzióval

6.1.7
* Hiányzó %-os áfakulcsok pótlása
* Kompatibilitás megjelölése legújabb Woo verzióval
* EU adószám validálás javítások

6.1.6
* E-mail számla csatolmány javítás
* Számlaértesítő szöveg beállítás javítás
* Törlőkód javítás

6.1.5
* wc_szamlazz_ipn_target_order_status filter
* Törlőkód opció termék beállítás haladó fülön(ha be van pipálva, akkor automatán ad hozzá törlőkódot a tételhez)
* 21%-os áfakulcs
* Kompatibilitás megjelölése legújabb Woo verzióval

6.1.4
* Szállítólevél is csatolható a levelekhez
* Rádió gombos céges számlázás választó klasszikus pénztár oldalon

6.1.3
* Szállítási díj áfa számolás hibajavítás legújabb Woo-ban

6.1.2
* Kompatibilitás legújabb Woo verzióval

6.1.1
* PHP notice javítás
* User role feltétel

6.1.0.1
* Woo Payments beállítás javítás

6.1
* Adószám bugfix blokkos pénztár oldalon
* wc_szamlazz_defer_invoice_in_bulk_action filter
* Translatepress kompatibilitás javítás

6.0.16
* JS bugfix
* Kompatibilitás megjelölése legújabb WP/WC verzióval

6.0.15
* PRO verzió modal javítás

6.0.14
* Aggregátor kód
* Kompatibilitás megjelölése legújabb WC verzióval

6.0.13
* WooCommerce Subscriptions adószám módosítás javítás

6.0.11 & 6.0.12
* Pénztár blokkos EU adószám mező javítás

6.0.10
* Blokkos pénztár adószám mező javítás

6.0.9
* EU adószám ellenőrzés javítások
* Szállítólevélből lehet újat csinálni akkor is, ha már van egy(felülírja a meglévőt)

6.0.8
* WPML kompatibilitás javítás

6.0.7
* Fatal error javítás pénztár oldalon, ha volt e-mail küldés beállítva

6.0.6
* Loco Translate kompatibilitás javítás
* Kompatibilitás megjelölése legújabb WP/WC verziókkal

6.0.5
* PHP Warning javítás adószámmal kapcsolatban

6.0.4
* Céges papírszámla/elektronikus számla kapcsoló javítás

6.0.3
* Pénztár blokk adószám javítás
* Kompatibilitás megjelölése legújabb WP/WC verziókkal

6.0.2
* Számlák a profilban funkció javítása
* Checkout Manager kompatibilitás javítása

6.0.1
* WooCommerce Subscriptions javítás
* Polylang javítás

6.0
* Új felület a csoportos címkegenerálásnál: nem tölti újra az oldalt, hanem egy ablakban látod pontosan a folyamatot
* Új csoportos nyomtatás és letöltés funkció: az összes dokumentum letölthető vagy nyomtatható egyben az oldal újratöltése nélkül
* Teljesen új beállítások oldal: Számlázz.hu főmenü és azon belül almenükre bontva a jobb átláthatóságért
* Beállíthatod, hogy milyen státuszba kerüljön a rendelés a számlagenerálás után(és oldal újratöltés nélkül látod a változást csoportos számlageneráláskor)
* Manuális számlakészítéskor is lehet fizetett(vagy nem fizetett) számlát generálni
* Az egyedi automatizálás opció lett az alapértelmezett(új telepítésnél már csak ez látszik)
* Szállítólevél automatizálás esetén is megadható a teljesítési idő
* Kisebb méret: 4.3mb helyett 2.5mb a bővítmény(pdf nyomtatásra JS-es megoldás PHP helyett)
* WooCommerce függőség megjelölése, így csak akkor lehet bekapcsolni, ha aktív a WooCommerce(ezzel együtt javítva egy aktiválási bug)
* Törölve az összes adminfelületen megjelenő értesítési üzenet
* A megjegyzésben lehet használni a {current_date} cserekódot, ami az aktuális dátumot írja ki(pl jótállási jegyhez hasznos lehet)
* Adószám mezőt kötelezővé lehet tenni
* EUK áfakulcs javítása saját EU adószám bekérése esetén
* Javítva egy bug az áfakulcs felülírással kapcsolatban
* Adószám blokk javítás Firefox-ban
* EU adószám ellenőrzés javítása bizonyos szerverkörnyezetekben
* Hibajavítás az adatok felülírásánál a feltételek beállításaiban
* Hibajavítás a feltételes mód beállításokban
* Gyűjtőszámla generálás látszik HPOS esetén is
* Régi kompatibilitásból megmaradt kódok törlése
* Adószám hibajavítás blokkos pénztároldalon
* wc_szamlazz_vat_number_validation_results filterrel módosítható az adószám validálás eredménye
* WooCommerce Subscriptions kompatibilitás javítás: meglévő díjbekérőt is letörli új előfizetési ciklusban
* Minimum PHP(7.4) és WP(6.0) verzió beállítása
* Kompatibilitás megjelölése legújabb WP/WC verziókkal