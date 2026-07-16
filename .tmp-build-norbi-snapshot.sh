#!/usr/bin/env zsh
set -euo pipefail

captured_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)

jq -n \
  --arg captured_at "$captured_at" \
  --slurpfile models /tmp/norbiphone-iphone-model-matrix.json \
  --slurpfile observations /tmp/norbiphone-standard-observations.jsonl \
  '
  def flattened_observations:
    [
      $observations[] as $observation
      | $observation.payout_modes[]
      | {
          source: $observation.source,
          source_model_label: $observation.source_model_label,
          canonical_candidate_model_key: ($observation.source_model_label | ascii_downcase | gsub("[^a-z0-9]+"; "_") | sub("_$"; "")),
          storage_gb: ($observation.storage_label | if endswith("TB") then (sub("TB$"; "") | tonumber * 1024) else (sub("GB$"; "") | tonumber) end),
          standardized_scenario_id: $observation.standardized_scenario_id,
          questionnaire_answers: $observation.questionnaire_answers,
          source_payout_mode: .source_payout_mode,
          amount_minor: (.amount_minor / 100 | floor),
          currency: .currency,
          preliminary: .preliminary,
          observed_at: $observation.observed_at,
          evidence_method: $observation.evidence_method,
          confidence: $observation.confidence
        }
    ];

  {
    schema_version: "1.0.0",
    source: "NorbiPhone",
    source_urls: [
      "https://norbiphone.hu/felvasarlas#kalkulator",
      "https://api.norbiphone.hu/api/calculator/models",
      "https://api.norbiphone.hu/api/calculator/calculate",
      "https://norbiphone.hu/static/js/main.bd5a2e63.js"
    ],
    captured_at: $captured_at,
    capture_method: "public_browser_dom_plus_same_session_bundle_and_public_api",
    source_page_marker: {
      calculator_bundle: "main.bd5a2e63.js",
      calculator_heading: "Mennyi a telefonod értéke?",
      public_api_host: "api.norbiphone.hu"
    },
    supported_device_categories: ["iPhone", "Samsung", "iPad", "Apple Watch", "Egyéb"],
    iphone_models: $models[0],
    configuration_options: {
      network_lock: ["Hálózatfüggetlen", "Kártyafüggő"],
      battery_health: {type: "integer_slider", minimum: 50, maximum: 100, default: 85},
      replacement_battery: [false, true],
      calculator_result_modes: ["cash", "trade_in_credit"]
    },
    condition_question_tree: [
      {step: "network_lock", question: "Hálózatfüggetlenség", options: [
        {label: "Hálózatfüggetlen", machine_value: "igen", canonical_key: "network_lock", canonical_value: false, eligibility_affecting: true},
        {label: "Kártyafüggő", machine_value: "nem", canonical_key: "network_lock", canonical_value: true, eligibility_affecting: true, branch_outcome: "rejected"}
      ]},
      {step: "liquid_damage", question: "Folyadékkal érintkezett?", options: [
        {label: "Nem érintkezett folyadékkal", machine_value: "nem", canonical_key: "liquid_damage", canonical_value: false, eligibility_affecting: true},
        {label: "Igen, volt folyadék közelében", machine_value: "igen", canonical_key: "liquid_damage", canonical_value: true, eligibility_affecting: true, branch_outcome: "rejected"}
      ]},
      {step: "screen_condition", question: "Milyen a kijelző felszíne?", options: [
        {label: "Tökéletes", machine_value: "hibatlan", canonical_key: "screen_condition", canonical_value: "like_new"},
        {label: "Alig látható kopás", machine_value: "hajszal", canonical_key: "screen_condition", canonical_value: "excellent"},
        {label: "Látható használati nyomok", machine_value: "karcos", canonical_key: "screen_condition", canonical_value: "good"},
        {label: "Erősen kopott", machine_value: "eros", canonical_key: "screen_condition", canonical_value: "damaged"},
        {label: "Repedt vagy törött", machine_value: "torott", canonical_key: "screen_condition", canonical_value: "damaged"}
      ]},
      {step: "display_functional", question: "Kijelző funkcionális hibák", multi_select: true, options: [
        {label: "Ismeretlen alkatrész figyelmeztetés", machine_value: "ismeretlen", canonical_key: "replacement_parts", canonical_value: "non_original", branch_outcome: "rejected"},
        {label: "Hibátlan állapot", machine_value: "none", canonical_key: "display_functional", canonical_value: true},
        {label: "Érintés nem mindig reagál", machine_value: "touch", canonical_key: "touch_functional", canonical_value: false},
        {label: "Elszíneződött kijelző", machine_value: "yellow", canonical_key: "display_functional", canonical_value: false},
        {label: "Benyomott, deformált felület", machine_value: "dent", canonical_key: "display_functional", canonical_value: false},
        {label: "Kiégett vagy elhalt pixelek", machine_value: "dead", canonical_key: "display_functional", canonical_value: false}
      ]},
      {step: "frame_condition", question: "Hogyan néz ki a keret?", options: [
        {label: "Kifogástalan állapot", machine_value: "hibatlan", canonical_key: "frame_condition", canonical_value: "like_new"},
        {label: "Alig észrevehető kopás", machine_value: "hajszal", canonical_key: "frame_condition", canonical_value: "excellent"},
        {label: "Látható karcolások", machine_value: "karcos", canonical_key: "frame_condition", canonical_value: "good"},
        {label: "Ütődés vagy horpadás", machine_value: "torott", canonical_key: "bent_or_dented", canonical_value: true}
      ]},
      {step: "back_glass_condition", question: "Milyen a hátlap állapota?", options: [
        {label: "Tökéletes állapot", machine_value: "hibatlan", canonical_key: "back_glass_condition", canonical_value: "like_new"},
        {label: "Karcos hátlap", machine_value: "karcos", canonical_key: "back_glass_condition", canonical_value: "good"},
        {label: "Repedt vagy törött hátlap", machine_value: "torott", canonical_key: "back_glass_condition", canonical_value: "damaged"}
      ]},
      {step: "battery_health", question: "Akkumulátor állapot", options: [
        {label: "50–100% csúszka", machine_value: "integer_percent", canonical_key: "battery_health", canonical_value: "same_percent"},
        {label: "Nem gyári akkumulátor", machine_value: "aftermarket", canonical_key: "replacement_parts", canonical_value: "non_original"}
      ]},
      {step: "other_functional_issues", question: "Más problémák is vannak?", multi_select: true, options: [
        {label: "Minden rendben működik", machine_value: "none", canonical_key: "powers_on", canonical_value: true},
        {label: "Hangszóró problémás", machine_value: "sound", canonical_key: null, canonical_value: null},
        {label: "Szelfis kamera hibás", machine_value: "cam_f", canonical_key: "camera_functional", canonical_value: false},
        {label: "Hátlapi kamera hibás", machine_value: "cam_b", canonical_key: "camera_functional", canonical_value: false},
        {label: "Arcfelismerés / ujjlenyomat nem működik", machine_value: "facetouch", canonical_key: "face_id_functional", canonical_value: false},
        {label: "Felduzzadt akkumulátor", machine_value: "battery", canonical_key: null, canonical_value: null},
        {label: "Egyéb meghibásodás", machine_value: "other", canonical_key: null, canonical_value: null}
      ]},
      {step: "optional_contact_gate", question: "Email cím (nem kötelező)", options: [
        {label: "Kihagyom, mutasd az árat", machine_value: "skip", price_available_without_contact: true}
      ]},
      {step: "post_offer_intent", question: "Nem árképzési utókérdések", price_affecting: false, options: [
        {key: "selling_time", labels: ["Azonnal", "1–2 héten belül", "1 hónapon belül", "Csak kíváncsi voltam"]},
        {key: "selling_reason", labels: ["Új iPhone-ra váltanék", "Samsungra váltanék", "Készpénzre van szükségem", "Már nem használom", "Csak kíváncsi voltam"]},
        {key: "upgrade_target", labels: ["iPhone", "Samsung", "Xiaomi", "Még nem tudom"]},
        {key: "payout_type", labels: ["Beszámítással szeretném", "Készpénzes felvásárlást szeretnék"]}
      ]}
    ],
    payout_modes: [
      {source_mode: "cash", appleklinika_candidate_mode: "in_store_instant", timing: "azonnali készpénz helyszíni bevizsgálás után"},
      {source_mode: "trade_in_credit", appleklinika_candidate_mode: "trade_in", timing: "beszámítás új vagy használt készülék vásárlásakor"}
    ],
    handover_modes: [
      {source_mode: "in_store", locations: 2, city: "Orosháza", preliminary_before_handover: true}
    ],
    offer_semantics: {
      public_result: true,
      contact_required: false,
      preliminary: true,
      final_after_in_store_inspection: true,
      source_notice: "Az összeg tájékoztató jellegű; a pontos ajánlat bevizsgálás után készül."
    },
    raw_reference_observations: (
      flattened_observations
      + [
          {
            source: "NorbiPhone",
            source_model_label: "iPhone 13 Pro",
            canonical_candidate_model_key: "iphone_13_pro",
            storage_gb: 128,
            standardized_scenario_id: "iphone_reference_v1",
            questionnaire_answers: {network_lock: false, liquid_damage: false, screen_condition: "perfect", screen_functional: true, frame_condition: "perfect", back_glass_condition: "perfect", battery_health: 90, replacement_battery: false, other_defect_count: 0},
            source_payout_mode: "cash",
            amount_minor: 90000,
            currency: "HUF",
            preliminary: true,
            observed_at: $captured_at,
            evidence_method: "public_ui_and_public_calculator_api",
            confidence: "high"
          },
          {
            source: "NorbiPhone",
            source_model_label: "iPhone 13 Pro",
            canonical_candidate_model_key: "iphone_13_pro",
            storage_gb: 128,
            standardized_scenario_id: "iphone_reference_v1",
            questionnaire_answers: {network_lock: false, liquid_damage: false, screen_condition: "perfect", screen_functional: true, frame_condition: "perfect", back_glass_condition: "perfect", battery_health: 90, replacement_battery: false, other_defect_count: 0},
            source_payout_mode: "trade_in_credit",
            amount_minor: 100000,
            currency: "HUF",
            preliminary: true,
            observed_at: $captured_at,
            evidence_method: "public_ui_and_public_calculator_api",
            confidence: "high"
          }
        ]
    ),
    unsupported_or_inaccessible_paths: [
      {category: "rate_limit", detail: "Sequential enumeration stopped immediately when the public calculation endpoint returned HTTP 429 after 10 successful combinations; no retry was attempted."},
      {category: "unobserved_combinations", detail: "The remaining advertised model/storage combinations were not queried after the rate-limit signal."},
      {category: "one_factor_prices", detail: "No one-factor bulk price sweep was attempted after rate limiting."}
    ],
    recovery: {
      rendered_dom: "success",
      public_model_endpoint: "success",
      public_calculation_endpoint: "success_then_rate_limited",
      public_bundle_inspection: "success",
      captcha_or_authentication: false,
      contact_submitted: false,
      advertised_model_count: ($models[0] | length),
      advertised_model_storage_combination_count: ([$models[0][].storage_options[]] | length),
      standardized_api_combinations_observed_before_stop: ($observations | length)
    },
    evidence_and_confidence_notes: [
      {area: "model_and_storage_options", confidence: "high", basis: "Rendered calculator plus public model endpoint and public bundle storage matrix."},
      {area: "question_tree", confidence: "high", basis: "Normal rendered calculator path plus public bundle definitions."},
      {area: "iPhone 13 Pro 128GB reference offers", confidence: "high", basis: "Public UI result and matching public API result without contact submission."},
      {area: "bulk_price_coverage", confidence: "partial", basis: "Ten sequential public API combinations were captured before HTTP 429; automation stopped and was not retried."}
    ]
  }
  ' > docs/research/buyback/norbiphone-iphone-benchmark-2026-07-16.json

jq empty docs/research/buyback/norbiphone-iphone-benchmark-2026-07-16.json
