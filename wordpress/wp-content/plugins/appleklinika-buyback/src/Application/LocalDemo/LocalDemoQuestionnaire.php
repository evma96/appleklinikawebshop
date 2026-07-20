<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

/**
 * Single source of truth for the local customer-facing iPhone questionnaire.
 *
 * The class intentionally contains presentation metadata and the safe mapping
 * to the already existing canonical pricing conditions. It does not own any
 * monetary rule or pricing logic.
 */
final class LocalDemoQuestionnaire
{
    /** @return list<string> */
    public function panelOrder(): array
    {
        return [
            'model',
            'configuration',
            'liquid_contact',
            'screen_cosmetic',
            'frame_cosmetic',
            'back_cosmetic',
            'battery',
            'display_defects',
            'other_defects',
            'offers',
            'review',
        ];
    }

    /** @return array<string, array{step:int,title:string,short:string}> */
    public function panels(): array
    {
        return [
            'model' => ['step' => 1, 'title' => 'Válaszd ki az iPhone modellt', 'short' => 'Készülék'],
            'configuration' => ['step' => 2, 'title' => 'Add meg a készülék konfigurációját', 'short' => 'Konfiguráció'],
            'liquid_contact' => ['step' => 3, 'title' => 'Érte a készüléket folyadék?', 'short' => 'Folyadék'],
            'screen_cosmetic' => ['step' => 4, 'title' => 'Milyen állapotban van a kijelző?', 'short' => 'Kijelző'],
            'frame_cosmetic' => ['step' => 5, 'title' => 'Milyen állapotban van a készülék kerete?', 'short' => 'Keret'],
            'back_cosmetic' => ['step' => 6, 'title' => 'Milyen állapotban van a hátlap?', 'short' => 'Hátlap'],
            'battery' => ['step' => 7, 'title' => 'Milyen az akkumulátor állapota?', 'short' => 'Akkumulátor'],
            'display_defects' => ['step' => 8, 'title' => 'Tapasztalsz hibát a kijelző működésében?', 'short' => 'Kijelzőhibák'],
            'other_defects' => ['step' => 9, 'title' => 'Van más ismert hibája a készüléknek?', 'short' => 'Egyéb hibák'],
            'offers' => ['step' => 10, 'title' => 'Válaszd ki a számodra megfelelő ajánlatot', 'short' => 'Ajánlat'],
            'review' => ['step' => 11, 'title' => 'Ellenőrizd az összefoglalót', 'short' => 'Összegzés'],
        ];
    }

    /** @return array{step:int,title:string,short:string} */
    public function panel(string $key): array
    {
        $panels = $this->panels();
        if (! isset($panels[$key])) {
            throw new \InvalidArgumentException('Unknown local demo questionnaire panel.');
        }

        return $panels[$key];
    }

    /** @return array<string, array<string, mixed>> */
    public function questions(): array
    {
        return [
            'network_status' => [
                'panel' => 'configuration',
                'type' => 'single',
                'label' => 'Hálózatfüggetlen a készülék?',
                'helper' => 'A jelenlegi helyi demó kizárólag hálózatfüggetlen iPhone készülékekre ad automatikus előzetes ajánlatot.',
                'default' => 'unlocked',
                'options' => [
                    'unlocked' => [
                        'label' => 'Hálózatfüggetlen',
                        'helper' => 'A készülék bármelyik támogatott szolgáltató SIM-kártyájával használható.',
                    ],
                    'locked' => [
                        'label' => 'Hálózatfüggő',
                        'helper' => 'A készülék csak egy adott szolgáltató hálózatán használható.',
                    ],
                ],
            ],
            'liquid_exposure' => [
                'panel' => 'liquid_contact',
                'type' => 'single',
                'label' => 'Érte a készüléket folyadék, pára vagy nedvesség?',
                'helper' => 'Ide tartozik a vízzel vagy más folyadékkal való érintkezés, beázás, korrózió vagy korábbi folyadékkár miatti javítás.',
                'default' => 'no',
                'options' => [
                    'no' => [
                        'label' => 'Nem',
                        'helper' => 'Nincs ismert folyadék-, pára- vagy korróziós károsodás.',
                    ],
                    'yes_unknown' => [
                        'label' => 'Igen vagy nem tudom biztosan',
                        'helper' => 'A pontos állapotot személyes bevizsgálás során ellenőrizzük.',
                    ],
                ],
            ],
            'screen_condition' => $this->screenConditionQuestion(),
            'frame_condition' => $this->frameConditionQuestion(),
            'back_glass_condition' => $this->backGlassConditionQuestion(),
            'battery_health' => [
                'panel' => 'battery',
                'type' => 'range',
                'label' => 'Akkumulátor állapota (%)',
                'helper' => 'Az értéket a Beállítások / Akkumulátor / Akku állapota és töltés menüben találod.',
                'default' => 90,
                'min' => 70,
                'max' => 100,
            ],
            'display_defects' => [
                'panel' => 'display_defects',
                'type' => 'multi',
                'label' => 'Jelöld az összes tapasztalt kijelzőhibát',
                'helper' => 'Több hibát is megjelölhetsz. A „Nincs ismert kijelzőhiba” választás kizárja a többi lehetőséget.',
                'default' => ['none'],
                'exclusive' => 'none',
                'options' => [
                    'none' => [
                        'label' => 'Nincs ismert kijelzőhiba',
                        'helper' => 'A kép, a fényerő és az érintés megfelelően működik.',
                    ],
                    'touch' => [
                        'label' => 'Nem reagál megfelelően az érintésre',
                        'helper' => 'A kijelző egyes pontjai, gesztusai vagy a gépelés nem működik megfelelően.',
                    ],
                    'yellowing' => [
                        'label' => 'Elszíneződött vagy besárgult a kijelző',
                        'helper' => 'A fehér felületek vagy a megjelenített színek eltérnek a normálistól.',
                    ],
                    'deformed' => [
                        'label' => 'Deformált vagy benyomódott a kijelző',
                        'helper' => 'A panel felülete vagy illeszkedése láthatóan megváltozott.',
                    ],
                    'pixels' => [
                        'label' => 'Kiégett, villogó vagy nem működő pixelek',
                        'helper' => 'Pontszerű, villogó vagy folyamatos képhiba látható.',
                    ],
                ],
            ],
            'other_defects' => [
                'panel' => 'other_defects',
                'type' => 'multi',
                'label' => 'Jelöld az összes ismert működési hibát',
                'helper' => 'Több hiba is megadható. A „Nincs ismert működési hiba” választás kizárja a többi lehetőséget.',
                'default' => ['none'],
                'exclusive' => 'none',
                'options' => [
                    'none' => [
                        'label' => 'Nincs ismert működési hiba',
                        'helper' => 'A felsorolt funkciók megfelelően működnek.',
                    ],
                    'audio' => [
                        'label' => 'A hang halk, torz vagy nem megfelelő',
                        'helper' => 'Hívásnál vagy médialejátszásnál hanghiba tapasztalható.',
                    ],
                    'front_camera' => [
                        'label' => 'Az előlapi kamera hibás',
                        'helper' => 'A szelfikamera képe vagy működése nem megfelelő.',
                    ],
                    'rear_camera' => [
                        'label' => 'A hátlapi kamera hibás',
                        'helper' => 'Egy vagy több hátlapi kamera nem működik megfelelően.',
                    ],
                    'face_id' => [
                        'label' => 'A Face ID nem működik megfelelően',
                        'helper' => 'Az arcfelismerés hibás, bizonytalan vagy nem állítható be.',
                    ],
                    'camera_lens' => [
                        'label' => 'Sérült kameralencse',
                        'helper' => 'A kamera körüli üveg repedt, törött vagy erősen karcos.',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function questionsForPanel(string $panel): array
    {
        return array_filter(
            $this->questions(),
            static fn (array $question): bool => $question['panel'] === $panel
        );
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        $defaults = [];
        foreach ($this->questions() as $key => $question) {
            $defaults[$key] = $question['default'];
        }

        return $defaults;
    }

    /** @param array<string, mixed> $input @return array<string, string> */
    public function validate(array $input): array
    {
        $errors = [];
        foreach ($this->questions() as $key => $question) {
            if (! array_key_exists($key, $input)) {
                $errors[$key] = 'Hiányzó válasz: ' . $question['label'] . '.';
                continue;
            }

            if ($question['type'] === 'range') {
                $value = filter_var($input[$key], FILTER_VALIDATE_INT);
                if ($value === false || $value < (int) $question['min'] || $value > (int) $question['max']) {
                    $errors[$key] = 'Az akkumulátor állapota ' . $question['min'] . ' és ' . $question['max'] . '% közötti érték legyen.';
                }
                continue;
            }

            $allowed = array_keys($question['options']);
            if ($question['type'] === 'multi') {
                $values = is_array($input[$key]) ? array_map('strval', $input[$key]) : [(string) $input[$key]];
                if ($values === [] || array_diff($values, $allowed) !== []) {
                    $errors[$key] = 'Érvénytelen választás: ' . $question['label'] . '.';
                }
                continue;
            }

            if (! is_scalar($input[$key]) || ! in_array((string) $input[$key], $allowed, true)) {
                $errors[$key] = 'Érvénytelen választás: ' . $question['label'] . '.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function sanitize(array $input): array
    {
        $state = $this->defaults();
        foreach ($this->questions() as $key => $question) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            if ($question['type'] === 'range') {
                $state[$key] = max((int) $question['min'], min((int) $question['max'], (int) $input[$key]));
                continue;
            }

            $allowed = array_keys($question['options']);
            if ($question['type'] === 'multi') {
                $values = is_array($input[$key]) ? $input[$key] : [$input[$key]];
                $values = array_values(array_unique(array_intersect($allowed, array_map('strval', $values))));
                $exclusive = (string) $question['exclusive'];
                $state[$key] = in_array($exclusive, $values, true) || $values === []
                    ? [$exclusive]
                    : array_values(array_diff($values, [$exclusive]));
                continue;
            }

            $value = (string) $input[$key];
            if (in_array($value, $allowed, true)) {
                $state[$key] = $value;
            }
        }

        return $state;
    }

    /** @param array<string, mixed> $state @return array<string, int|bool|string> */
    public function mapToConditions(array $state): array
    {
        $state = $this->sanitize($state);
        $displayDefects = (array) $state['display_defects'];
        $otherDefects = (array) $state['other_defects'];

        return [
            // The verified reference flow does not ask separate power/output questions.
            // The demo therefore assumes these baseline functions work unless a visible
            // defect option maps to an existing canonical condition below.
            'powers_on' => true,
            'display_functional' => true,
            'touch_functional' => ! in_array('touch', $displayDefects, true),
            'face_id_functional' => ! in_array('face_id', $otherDefects, true),
            'camera_functional' => ! in_array('front_camera', $otherDefects, true)
                && ! in_array('rear_camera', $otherDefects, true),
            'charging_functional' => true,
            'battery_health' => (int) $state['battery_health'],
            'screen_condition' => (string) $state['screen_condition'],
            'frame_condition' => (string) $state['frame_condition'],
            'back_glass_condition' => (string) $state['back_glass_condition'],
            'camera_lens_condition' => in_array('camera_lens', $otherDefects, true) ? 'damaged' : 'excellent',
            'bent_or_dented' => false,
            'liquid_damage' => $state['liquid_exposure'] === 'yes_unknown',
            'motherboard_issue' => false,
            'replacement_parts' => 'none_known',
        ];
    }

    /** @param array<string, mixed> $state */
    public function eligibilityError(array $state): ?string
    {
        $state = $this->sanitize($state);

        return $state['network_status'] === 'locked'
            ? 'A helyi demó jelenleg csak hálózatfüggetlen iPhone készülékeket tud automatikusan értékelni.'
            : null;
    }

    /** @param array<string, mixed> $state @return list<string> */
    public function manualReviewReasons(array $state): array
    {
        $state = $this->sanitize($state);
        $reasons = [];

        if ($state['liquid_exposure'] === 'yes_unknown') {
            $reasons[] = 'A lehetséges folyadék- vagy párakár kézi bevizsgálást igényel.';
        }
        if (array_diff((array) $state['display_defects'], ['none', 'touch']) !== []) {
            $reasons[] = 'A megjelölt kijelzőhiba egyedi bevizsgálást igényel.';
        }
        if (in_array('audio', (array) $state['other_defects'], true)) {
            $reasons[] = 'A hanghiba egyedi bevizsgálást igényel.';
        }

        return array_values(array_unique($reasons));
    }

    /** @param array<string, mixed> $state @return array<string, array<string, string>> */
    public function summary(array $state, string $model, string $storage): array
    {
        $state = $this->sanitize($state);

        return [
            'Készülék' => ['Modell' => $model],
            'Konfiguráció' => [
                'Tárhely' => $storage,
                'Hálózat' => $this->answerLabel('network_status', $state['network_status']),
            ],
            'Állapot' => [
                'Folyadék / pára' => $this->answerLabel('liquid_exposure', $state['liquid_exposure']),
                'Kijelző' => $this->answerLabel('screen_condition', $state['screen_condition']),
                'Keret' => $this->answerLabel('frame_condition', $state['frame_condition']),
                'Hátlap' => $this->answerLabel('back_glass_condition', $state['back_glass_condition']),
            ],
            'Akkumulátor' => ['Állapot' => (int) $state['battery_health'] . '%'],
            'Kijelzőhibák' => [
                'Megjelölt válaszok' => $this->answerLabels('display_defects', (array) $state['display_defects']),
            ],
            'Egyéb hibák' => [
                'Megjelölt válaszok' => $this->answerLabels('other_defects', (array) $state['other_defects']),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function screenConditionQuestion(): array
    {
        return [
            'panel' => 'screen_cosmetic',
            'type' => 'single',
            'label' => 'Milyen állapotban van a kijelző?',
            'helper' => 'Nézd meg kikapcsolt és bekapcsolt állapotban, erős fényben is.',
            'default' => 'excellent',
            'options' => [
                'like_new' => ['label' => 'Hibátlan', 'helper' => 'Nem látható karc, repedés vagy más használati nyom.'],
                'excellent' => ['label' => 'Apró használati nyomok', 'helper' => 'Néhány nagyon apró, bekapcsolt kijelzőn alig észrevehető felületi karc látható.'],
                'very_good' => ['label' => 'Intenzívebb használati nyomok', 'helper' => 'Több látható karc vagy kopás található rajta, repedés nélkül.'],
                'good' => ['label' => 'Erősen kopott', 'helper' => 'Mélyebb vagy számos karc látható, amelyek használat közben is észrevehetők.'],
                'damaged' => ['label' => 'Törött vagy repedt', 'helper' => 'A kijelző repedt, törött vagy olyan súlyosan sérült, hogy működési kockázatot jelent.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function frameConditionQuestion(): array
    {
        return [
            'panel' => 'frame_cosmetic',
            'type' => 'single',
            'label' => 'Milyen állapotban van a készülék kerete?',
            'helper' => 'Vizsgáld meg a sarkokat, az éleket és a gombok környékét.',
            'default' => 'excellent',
            'options' => [
                'like_new' => ['label' => 'Hibátlan', 'helper' => 'A keret újszerű, karcolás, kopás vagy horpadás nélkül.'],
                'excellent' => ['label' => 'Apró használati nyomok', 'helper' => 'Néhány alig észrevehető felületi karc vagy apró pont látható.'],
                'very_good' => ['label' => 'Intenzívebb használati nyomok', 'helper' => 'Több látható karc, festékkopás vagy kisebb ütődés található rajta.'],
                'good' => ['label' => 'Erősen használt', 'helper' => 'Jelentős kopás, mélyebb karcok vagy kisebb horpadások láthatók.'],
                'damaged' => ['label' => 'Sérült vagy deformált', 'helper' => 'A keret repedt, erősen horpadt, hajlott vagy láthatóan deformált.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function backGlassConditionQuestion(): array
    {
        return [
            'panel' => 'back_cosmetic',
            'type' => 'single',
            'label' => 'Milyen állapotban van a hátlap?',
            'helper' => 'Nézd meg erős fényben, több szögből a karcokat, lepattanásokat és repedéseket.',
            'default' => 'excellent',
            'options' => [
                'like_new' => ['label' => 'Hibátlan', 'helper' => 'Nem látható karc, lepattanás vagy repedés.'],
                'excellent' => ['label' => 'Apró használati nyomok', 'helper' => 'Néhány nagyon apró, alig észrevehető felületi karc látható.'],
                'very_good' => ['label' => 'Intenzívebb használati nyomok', 'helper' => 'Több látható karc vagy kopás található rajta, repedés nélkül.'],
                'good' => ['label' => 'Erősen használt', 'helper' => 'Mélyebb karcok, kopás vagy kisebb lepattanás látható, de a hátlap nem törött.'],
                'damaged' => ['label' => 'Törött vagy repedt', 'helper' => 'A hátlap üvege repedt, törött vagy jelentősen sérült.'],
            ],
        ];
    }

    private function answerLabel(string $key, mixed $value): string
    {
        $question = $this->questions()[$key];

        return (string) ($question['options'][(string) $value]['label'] ?? $value);
    }

    /** @param list<string> $values */
    private function answerLabels(string $key, array $values): string
    {
        return implode(', ', array_map(fn (string $value): string => $this->answerLabel($key, $value), $values));
    }
}
