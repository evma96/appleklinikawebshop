<?php

declare(strict_types=1);

namespace AppleKlinika\Buyback\Application\LocalDemo;

use AppleKlinika\Buyback\Domain\Pricing\PricingRuleKind;
use AppleKlinika\Buyback\Domain\Pricing\SystemDefaultQuestionnairePolicy;

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
            'display_defects',
            'frame_cosmetic',
            'back_cosmetic',
            'battery',
            'service_history',
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
            'display_defects' => ['step' => 5, 'title' => 'Tapasztalsz hibát a kijelző működésében?', 'short' => 'Kijelzőhibák'],
            'frame_cosmetic' => ['step' => 6, 'title' => 'Milyen állapotban van a készülék kerete?', 'short' => 'Keret'],
            'back_cosmetic' => ['step' => 7, 'title' => 'Milyen állapotban van a hátlap?', 'short' => 'Hátlap'],
            'battery' => ['step' => 8, 'title' => 'Milyen az akkumulátor állapota?', 'short' => 'Akkumulátor'],
            'service_history' => ['step' => 9, 'title' => 'Találsz bejegyzést az Alkatrész- és szervizelési előzmények között?', 'short' => 'Szervizelőzmények'],
            'other_defects' => ['step' => 10, 'title' => 'Van más ismert hibája a készüléknek?', 'short' => 'Egyéb hibák'],
            'offers' => ['step' => 11, 'title' => 'Válaszd ki a számodra megfelelő ajánlatot', 'short' => 'Ajánlat'],
            'review' => ['step' => 12, 'title' => 'Ellenőrizd az összefoglalót', 'short' => 'Összegzés'],
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
                'helper' => 'A felvásárlási folyamat kizárólag hálózatfüggetlen iPhone készülékekre ad automatikus előzetes ajánlatot.',
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
                    'image_brightness' => [
                        'label' => 'A kép vagy a fényerő működése hibás',
                        'helper' => 'A megjelenített kép vagy a kijelző fényereje nem működik megfelelően.',
                    ],
                ],
            ],
            'service_history' => [
                'panel' => 'service_history',
                'type' => 'single',
                'label' => 'Találsz bejegyzést az Alkatrész- és szervizelési előzmények között?',
                'helper' => 'Ebből látható, hogy cseréltek-e fontos alkatrészt, és az eredeti, használt, ismeretlen vagy még befejezetlen javításként szerepel-e.',
                'default' => 'none_known',
                'options' => [
                    'none_known' => ['label' => 'Nincs ilyen bejegyzés', 'helper' => 'Tudomásom szerint a készüléken nem történt rögzített alkatrészcsere.'],
                    'original_repair' => ['label' => 'Eredeti Apple-alkatrésszel javították', 'helper' => 'Az érintett alkatrész mellett eredeti Apple-alkatrészre utaló jelölés látható.'],
                    'used_original' => ['label' => 'Használt eredeti Apple-alkatrész szerepel', 'helper' => 'Az alkatrész eredeti, de korábban más készülékben is használhatták.'],
                    'unknown' => ['label' => 'Ismeretlen vagy nem ellenőrzött alkatrész szerepel', 'helper' => 'Az iPhone nem tudja eredetiként vagy megfelelően ellenőrzöttként azonosítani az alkatrészt.'],
                    'repair_incomplete' => ['label' => '„Javítás befejezése” vagy más szervizüzenet látható', 'helper' => 'A javítás, párosítás vagy ellenőrzés még nem fejeződött be teljesen.'],
                    'non_original' => ['label' => 'Tudomásom szerint nem eredeti alkatrész került bele', 'helper' => 'A készüléket utángyártott vagy nem eredeti alkatrésszel javították.'],
                    'unsure' => ['label' => 'Nem tudom biztosan', 'helper' => 'Nem tudom ellenőrizni vagy értelmezni a megjelenő információt.'],
                ],
            ],
            'affected_parts' => [
                'panel' => 'service_history',
                'type' => 'multi',
                'label' => 'Melyik alkatrészt érinti?',
                'helper' => 'Jelölj meg legalább egy érintett alkatrészt.',
                'default' => [],
                'conditional_on' => 'service_history',
                'conditional_except' => 'none_known',
                'options' => [
                    'battery' => ['label' => 'Akkumulátor', 'helper' => ''],
                    'display' => ['label' => 'Kijelző', 'helper' => ''],
                    'rear_camera' => ['label' => 'Hátlapi kamera', 'helper' => ''],
                    'front_camera_truedepth' => ['label' => 'Előlapi kamera / TrueDepth rendszer', 'helper' => ''],
                    'other' => ['label' => 'Egyéb alkatrész', 'helper' => ''],
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

    /**
     * The public visual-state contract is owned by the same questionnaire as
     * the customer-facing answers.  It is presentation metadata only: none of
     * these values are accepted by, or passed to, pricing.
     *
     * @return list<array{panel:string,question_key:string,question_label:string,answer_key:string,answer_label:string,visual_key:string}>
     */
    public function visualStateAnswers(): array
    {
        $states = [];

        foreach ($this->questions() as $questionKey => $question) {
            foreach (($question['options'] ?? []) as $answerKey => $answer) {
                if (! isset($answer['visual_key']) || $answer['visual_key'] === '') {
                    continue;
                }

                $states[] = [
                    'panel' => (string) $question['panel'],
                    'question_key' => (string) $questionKey,
                    'question_label' => (string) $question['label'],
                    'answer_key' => (string) $answerKey,
                    'answer_label' => (string) $answer['label'],
                    'visual_key' => (string) $answer['visual_key'],
                ];
            }
        }

        return $states;
    }

    /**
     * Business-facing condition-editor metadata derived from this same public
     * questionnaire. The admin never owns a second list of questions or
     * answers: it only receives the supported existing pricing-condition
     * mapping or an immutable system outcome for each public answer.
     *
     * @return list<array{panel:string,panel_title:string,question_key:string,label:string,helper:string,conditional_on:?string,conditional_except:?string,options:list<array{answer_key:string,label:string,configurable:bool,editor_kind:string,condition_key?:string,comparison_value?:int|bool|string,system_outcome?:string}>}>
     */
    public function conditionEditorQuestions(): array
    {
        $items = [];
        foreach ($this->panelOrder() as $panelKey) {
            if (in_array($panelKey, ['model', 'battery', 'offers', 'review'], true)) {
                continue;
            }

            foreach ($this->questionsForPanel($panelKey) as $questionKey => $question) {
                if (! isset($question['options'])) {
                    continue;
                }

                $options = [];
                foreach ($question['options'] as $answerKey => $answer) {
                    $options[] = $this->conditionEditorOption((string) $questionKey, (string) $answerKey, (string) $answer['label']);
                }

                $items[] = [
                    'panel' => $panelKey,
                    'panel_title' => $this->panel($panelKey)['title'],
                    'question_key' => (string) $questionKey,
                    'label' => (string) $question['label'],
                    'helper' => $questionKey === 'affected_parts'
                        ? 'Az alkatrész önmagában nem módosítja az ajánlatot. Az alkatrészenkénti következményeket a szervizelőzmény egyes válaszainál állíthatod be.'
                        : (string) ($question['helper'] ?? ''),
                    'conditional_on' => isset($question['conditional_on']) ? (string) $question['conditional_on'] : null,
                    'conditional_except' => isset($question['conditional_except']) ? (string) $question['conditional_except'] : null,
                    'options' => $options,
                ];
            }
        }

        return $items;
    }

    /**
     * Canonical editor metadata for rules that refine one service-history
     * answer by the selected affected component. It is derived from the same
     * public questionnaire, so the admin cannot drift into a second key list.
     *
     * @return array{service_history:list<array{answer_key:string,label:string}>,components:list<array{component_key:string,label:string,allows_monetary:bool}>}
     */
    public function serviceHistoryComponentRuleMetadata(): array
    {
        $questions = $this->questions();
        $serviceHistory = [];
        foreach ($questions['service_history']['options'] as $answerKey => $answer) {
            if ($answerKey === 'none_known') {
                continue;
            }
            $serviceHistory[] = ['answer_key' => (string) $answerKey, 'label' => (string) $answer['label']];
        }

        $components = [];
        foreach ($questions['affected_parts']['options'] as $componentKey => $component) {
            $components[] = [
                'component_key' => (string) $componentKey,
                'label' => (string) $component['label'],
                'allows_monetary' => $componentKey !== 'other',
            ];
        }

        return ['service_history' => $serviceHistory, 'components' => $components];
    }

    /** @param array<string,mixed> $state @return list<string> */
    public function affectedPartKeys(array $state): array
    {
        $state = $this->sanitize($state);
        return $this->serviceHistoryRequiresAffectedParts($state)
            ? array_values(array_unique(array_map('strval', (array) $state['affected_parts'])))
            : [];
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
                if (isset($question['conditional_on']) && ! $this->serviceHistoryRequiresAffectedParts($input)) {
                    continue;
                }
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
                if (isset($question['conditional_on']) && ! $this->serviceHistoryRequiresAffectedParts($input)) {
                    continue;
                }
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
                $exclusive = isset($question['exclusive']) ? (string) $question['exclusive'] : null;
                $state[$key] = $exclusive !== null && (in_array($exclusive, $values, true) || $values === [])
                    ? [$exclusive]
                    : array_values($exclusive === null ? $values : array_diff($values, [$exclusive]));
                continue;
            }

            $value = (string) $input[$key];
            if (in_array($value, $allowed, true)) {
                $state[$key] = $value;
            }
        }

        if (! $this->serviceHistoryRequiresAffectedParts($state)) {
            $state['affected_parts'] = [];
        }

        return $state;
    }

    /** @param array<string,mixed> $state */
    public function serviceHistoryRequiresAffectedParts(array $state): bool
    {
        return $this->normalizedServiceHistory($state) !== 'none_known';
    }

    /** @param array<string,mixed> $state */
    private function normalizedServiceHistory(array $state): string
    {
        $value = (string) ($state['service_history'] ?? '');
        return array_key_exists($value, $this->questions()['service_history']['options']) ? $value : '';
    }

    /** @param array<string, mixed> $state @return array<string, int|bool|string> */
    public function mapToConditions(array $state): array
    {
        $state = $this->sanitize($state);
        $displayDefects = (array) $state['display_defects'];
        $otherDefects = (array) $state['other_defects'];

        $summary = [
            // The verified reference flow does not ask separate power/output questions.
            // The demo therefore assumes these baseline functions work unless a visible
            // defect option maps to an existing canonical condition below.
            'powers_on' => true,
            'display_functional' => true,
            'touch_functional' => ! in_array('touch', $displayDefects, true),
            'face_id_functional' => ! in_array('face_id', $otherDefects, true),
            'camera_functional' => ! in_array('front_camera', $otherDefects, true)
                && ! in_array('rear_camera', $otherDefects, true),
            'front_camera_functional' => ! in_array('front_camera', $otherDefects, true),
            'rear_camera_functional' => ! in_array('rear_camera', $otherDefects, true),
            'audio_functional' => ! in_array('audio', $otherDefects, true),
            'charging_functional' => true,
            'battery_health' => (int) $state['battery_health'],
            'screen_condition' => (string) $state['screen_condition'],
            'frame_condition' => (string) $state['frame_condition'],
            'back_glass_condition' => (string) $state['back_glass_condition'],
            'camera_lens_condition' => in_array('camera_lens', $otherDefects, true) ? 'damaged' : 'excellent',
            'bent_or_dented' => false,
            'liquid_damage' => $state['liquid_exposure'] === 'yes_unknown',
            'network_unlocked' => $state['network_status'] === 'unlocked',
            'display_yellowing' => in_array('yellowing', $displayDefects, true),
            'display_deformed' => in_array('deformed', $displayDefects, true),
            'display_dead_pixels' => in_array('pixels', $displayDefects, true),
            'display_image_brightness_functional' => ! in_array('image_brightness', $displayDefects, true),
            'motherboard_issue' => false,
            'replacement_parts' => match ((string) $state['service_history']) {
                'original_repair', 'used_original', 'non_original', 'unknown', 'repair_incomplete', 'unsure' => (string) $state['service_history'],
                default => 'none_known',
            },
        ];

        return $summary;
    }

    /** @param array<string, mixed> $state */
    public function eligibilityError(array $state): ?string
    {
        $state = $this->sanitize($state);

        return null;
    }

    /** @param array<string, mixed> $state @return list<string> */
    public function manualReviewReasons(array $state): array
    {
        $state = $this->sanitize($state);
        return [];
    }

    /**
     * Turns an effective pricing condition or questionnaire reason into the
     * concise wording shown to a customer on the inspection-required result.
     *
     * @param array<string,mixed> $state
     */
    public function publicManualReviewReason(?string $conditionKey, string $reason, array $state, ?string $affectedComponentKey = null): string
    {
        $state = $this->sanitize($state);

        if ($conditionKey === 'replacement_parts' && $affectedComponentKey !== null) {
            $component = $this->questions()['affected_parts']['options'][$affectedComponentKey]['label'] ?? null;
            if (is_string($component) && $component !== '') {
                return $component . ' szervizelőzménye';
            }
        }

        if ($conditionKey === null) {
            $normalizedReason = function_exists('mb_strtolower') ? mb_strtolower($reason) : strtolower($reason);
            if ($state['liquid_exposure'] === 'yes_unknown' && str_contains($normalizedReason, 'folyad')) {
                return 'Lehetséges folyadékérintkezés';
            }
            if ($state['screen_condition'] === 'damaged' && str_contains($normalizedReason, 'kijelző')) {
                return $this->answerLabel('screen_condition', $state['screen_condition']) . ' kijelző';
            }
            if ($state['frame_condition'] === 'damaged' && str_contains($normalizedReason, 'keret')) {
                return $this->answerLabel('frame_condition', $state['frame_condition']) . ' keret';
            }
        }

        return match ($conditionKey) {
            'liquid_damage' => 'Lehetséges folyadékérintkezés',
            'screen_condition' => $this->answerLabel('screen_condition', $state['screen_condition']) . ' kijelző',
            'frame_condition' => $this->answerLabel('frame_condition', $state['frame_condition']) . ' keret',
            'back_glass_condition' => $this->answerLabel('back_glass_condition', $state['back_glass_condition']) . ' hátlap',
            'camera_lens_condition' => 'Sérült kameralencse',
            'touch_functional' => 'Kijelzőérintési hiba',
            'camera_functional' => 'Kamerahiba',
            'face_id_functional' => 'Face ID hiba',
            'replacement_parts' => 'Bizonytalan alkatrész- vagy szervizelőzmény',
            default => match ($reason) {
                'A lehetséges folyadék- vagy párakár kézi bevizsgálást igényel.' => 'Lehetséges folyadékérintkezés',
                'A megjelölt kijelzőhiba egyedi bevizsgálást igényel.' => 'Kijelző működési hiba',
                'A hanghiba egyedi bevizsgálást igényel.' => 'Hanghiba',
                'Az alkatrész- és szervizelési előzmények kézi bevizsgálást igényelnek.' => 'Bizonytalan alkatrész- vagy szervizelőzmény',
                default => $reason,
            },
        };
    }

    /** @param array<string, mixed> $state @return array<string, array<string, string>> */
    public function summary(array $state, string $model, string $storage, string $color = ''): array
    {
        $state = $this->sanitize($state);

        $summary = [
            'Készülék' => ['Modell' => $model],
            'Konfiguráció' => [
                'Tárhely' => $storage,
                'Szín' => $color !== '' ? $color : 'Nincs megadva',
                'Hálózat' => $this->answerLabel('network_status', $state['network_status']),
            ],
            'Állapot' => [
                'Folyadékérintkezés' => $this->answerLabel('liquid_exposure', $state['liquid_exposure']),
                'Kijelző' => $this->answerLabel('screen_condition', $state['screen_condition']),
                'Keret' => $this->answerLabel('frame_condition', $state['frame_condition']),
                'Hátlap' => $this->answerLabel('back_glass_condition', $state['back_glass_condition']),
            ],
            'Akkumulátor' => ['Állapot' => (int) $state['battery_health'] . '%'],
            'Kijelzőhibák' => [
                'Megjelölt válaszok' => $this->answerLabels('display_defects', (array) $state['display_defects']),
            ],
            'Alkatrész- és szervizelési előzmények' => [
                'Állapot' => $this->answerLabel('service_history', $state['service_history']),
                'Kézi bevizsgálás' => in_array((string) $state['service_history'], ['used_original', 'unknown', 'repair_incomplete', 'non_original', 'unsure'], true) ? 'Szükséges' : 'Nem szükséges',
            ],
            'Egyéb hibák' => [
                'Megjelölt válaszok' => $this->answerLabels('other_defects', (array) $state['other_defects']),
            ],
        ];
        if ($this->serviceHistoryRequiresAffectedParts($state)) {
            $summary['Alkatrész- és szervizelési előzmények']['Érintett alkatrészek'] = $this->answerLabels('affected_parts', (array) $state['affected_parts']);
        }

        return $summary;
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
                'like_new' => ['label' => 'Hibátlan', 'helper' => 'Nem látható karc, repedés vagy más használati nyom.', 'visual_key' => 'screen/flawless'],
                'excellent' => ['label' => 'Apró használati nyomok', 'helper' => 'Néhány nagyon apró, bekapcsolt kijelzőn alig észrevehető felületi karc látható.', 'visual_key' => 'screen/minor-wear'],
                'very_good' => ['label' => 'Intenzívebb használati nyomok', 'helper' => 'Több látható karc vagy kopás található rajta, repedés nélkül.', 'visual_key' => 'screen/heavier-wear'],
                'good' => ['label' => 'Erősen kopott', 'helper' => 'Mélyebb vagy számos karc látható, amelyek használat közben is észrevehetők.', 'visual_key' => 'screen/strongly-worn'],
                'damaged' => ['label' => 'Törött vagy repedt', 'helper' => 'A kijelző repedt, törött vagy olyan súlyosan sérült, hogy működési kockázatot jelent.', 'visual_key' => 'screen/cracked'],
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
                'like_new' => ['label' => 'Hibátlan', 'helper' => 'A keret újszerű, karcolás, kopás vagy horpadás nélkül.', 'visual_key' => 'frame/flawless'],
                'excellent' => ['label' => 'Apró használati nyomok', 'helper' => 'Néhány alig észrevehető felületi karc vagy apró pont látható.', 'visual_key' => 'frame/minor-wear'],
                'very_good' => ['label' => 'Intenzívebb használati nyomok', 'helper' => 'Több látható karc, festékkopás vagy kisebb ütődés található rajta.', 'visual_key' => 'frame/heavier-wear'],
                'good' => ['label' => 'Erősen használt', 'helper' => 'Jelentős kopás, mélyebb karcok vagy kisebb horpadások láthatók.', 'visual_key' => 'frame/strongly-worn'],
                'damaged' => ['label' => 'Sérült vagy deformált', 'helper' => 'A keret repedt, erősen horpadt, hajlott vagy láthatóan deformált.', 'visual_key' => 'frame/damaged'],
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
                'like_new' => ['label' => 'Hibátlan', 'helper' => 'Nem látható karc, lepattanás vagy repedés.', 'visual_key' => 'back-glass/flawless'],
                'excellent' => ['label' => 'Apró használati nyomok', 'helper' => 'Néhány nagyon apró, alig észrevehető felületi karc látható.', 'visual_key' => 'back-glass/minor-wear'],
                'very_good' => ['label' => 'Intenzívebb használati nyomok', 'helper' => 'Több látható karc vagy kopás található rajta, repedés nélkül.', 'visual_key' => 'back-glass/heavier-wear'],
                'good' => ['label' => 'Erősen használt', 'helper' => 'Mélyebb karcok, kopás vagy kisebb lepattanás látható, de a hátlap nem törött.', 'visual_key' => 'back-glass/strongly-worn'],
                'damaged' => ['label' => 'Törött vagy repedt', 'helper' => 'A hátlap üvege repedt, törött vagy jelentősen sérült.', 'visual_key' => 'back-glass/cracked'],
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

    /** @return array{answer_key:string,label:string,configurable:bool,editor_kind:string,condition_key?:string,comparison_value?:int|bool|string,system_outcome?:string} */
    private function conditionEditorOption(string $questionKey, string $answerKey, string $label): array
    {
        $target = match ($questionKey) {
            'screen_condition' => ['condition_key' => 'screen_condition', 'comparison_value' => $answerKey],
            'frame_condition' => ['condition_key' => 'frame_condition', 'comparison_value' => $answerKey],
            'back_glass_condition' => ['condition_key' => 'back_glass_condition', 'comparison_value' => $answerKey],
            'display_defects' => $answerKey === 'touch'
                ? ['condition_key' => 'touch_functional', 'comparison_value' => false]
                : null,
            'service_history' => $answerKey === 'original_repair'
                ? ['condition_key' => 'replacement_parts', 'comparison_value' => 'original_repair']
                : null,
            'other_defects' => match ($answerKey) {
                'front_camera', 'rear_camera' => ['condition_key' => 'camera_functional', 'comparison_value' => false],
                'face_id' => ['condition_key' => 'face_id_functional', 'comparison_value' => false],
                'camera_lens' => ['condition_key' => 'camera_lens_condition', 'comparison_value' => 'damaged'],
                default => null,
            },
            default => null,
        };

        $policy = (new SystemDefaultQuestionnairePolicy())->entryFor($questionKey, $answerKey);
        if ($policy !== null) {
            $target = ['condition_key' => $policy['condition_key'], 'comparison_value' => $policy['comparison_value']];
        }

        if ($target !== null) {
            return ['answer_key' => $answerKey, 'label' => $label, 'configurable' => true, 'editor_kind' => 'configurable'] + $target;
        }

        return [
            'answer_key' => $answerKey,
            'label' => $label,
            'configurable' => false,
            'editor_kind' => 'informational',
            'system_outcome' => $this->conditionEditorSystemOutcome($questionKey, $answerKey),
        ];
    }

    private function conditionEditorSystemOutcome(string $questionKey, string $answerKey): string
    {
        $policy = (new SystemDefaultQuestionnairePolicy())->entryFor($questionKey, $answerKey);
        if ($policy !== null) {
            return match ($policy['default_action']) {
                PricingRuleKind::MANUAL_REVIEW => 'Jelenlegi alapértelmezés: személyes bevizsgálás. Az árkönyvben felülírható.',
                PricingRuleKind::HARD_REJECT => 'Jelenlegi alapértelmezés: nem felvásárolható. Az árkönyvben felülírható.',
                default => 'Jelenlegi alapértelmezés: nincs változás. Az árkönyvben felülírható.',
            };
        }
        return match ($questionKey) {
            'service_history' => match ($answerKey) {
                'none_known' => 'Ilyenkor nem kell érintett alkatrészt megadni, és a korábban kijelölt alkatrészek törlődnek.',
                default => 'Ez a válasz önmagában nem hoz létre külön árazási szabályt.',
            },
            'affected_parts' => 'Az érintett alkatrész csak a kérdőív bevizsgálási információja; önálló árazási szabály nem tartozik hozzá.',
            default => 'Ez az adat a bevizsgálás részlete, önálló árazási szabály nem tartozik hozzá.',
        };
    }
}
