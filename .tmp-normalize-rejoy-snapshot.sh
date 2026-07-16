#!/usr/bin/env zsh
set -euo pipefail

source_file=docs/research/buyback/rejoy-iphone-benchmark-2026-07-16.json
target_file=/tmp/rejoy-iphone-benchmark-2026-07-16.normalized.json

jq '
  {
    schema_version: "1.0.0",
    source: .source,
    source_urls: [.source_url],
    captured_at: .captured_at,
    capture_method: .capture_method,
    source_page_marker: .page_marker,
    supported_device_categories: .supported_device_categories,
    iphone_models: .models,
    configuration_options: (.configuration_options + {
      standardized_reference_scenario: .standardized_reference_scenario,
      question_tree_coverage_status: .complete_question_tree.coverage_status,
      explicitly_unobserved_canonical_questions: .complete_question_tree.explicitly_unobserved_canonical_questions
    }),
    condition_question_tree: .complete_question_tree.nodes,
    payout_modes: .payout_modes,
    handover_modes: .handover_modes,
    offer_semantics: .offer_semantics,
    raw_reference_observations: (.raw_price_observations | map(.amount_minor = (.amount_minor / 100 | floor))),
    unsupported_or_inaccessible_paths: (.inaccessible_paths + .source_errors),
    evidence_and_confidence_notes: .confidence_and_evidence_notes,
    recovery: {
      rendered_dom: "success",
      model_and_configuration_capture: "success",
      standardized_price_capture: "blocked_by_contact_gate",
      negative_branch_capture: "stopped_after_two_control_errors",
      captcha_or_authentication: false,
      contact_submitted: false,
      advertised_model_count: (.models | length),
      advertised_model_storage_combination_count: ([.models[].storage_options_gb[]] | length)
    }
  }
' "$source_file" > "$target_file"

jq empty "$target_file"
mv "$target_file" "$source_file"
