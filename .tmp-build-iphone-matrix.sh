#!/usr/bin/env zsh
set -euo pipefail

jq -n \
  --slurpfile inventory /tmp/appleklinika-iphone-inventory.json \
  --slurpfile rejoy docs/research/buyback/rejoy-iphone-benchmark-2026-07-16.json \
  --slurpfile showme docs/research/buyback/showme-iphone-benchmark-2026-07-16.json \
  --slurpfile norbi docs/research/buyback/norbiphone-iphone-benchmark-2026-07-16.json \
  '
  def normalized_label:
    ascii_downcase
    | gsub("\\s+"; " ")
    | gsub("^ "; "")
    | gsub(" $"; "");

  def canonical_key($label):
    ($label | normalized_label) as $label_normalized
    | if $label_normalized == "iphone se 2020" then "iphone_se_2nd_generation"
      elif $label_normalized == "iphone se 2022" then "iphone_se_3rd_generation"
      elif $label_normalized == "iphone 17 air" or $label_normalized == "iphone air" then "iphone_air"
      else ($label_normalized | gsub("[^a-z0-9]+"; "_") | gsub("^_+|_+$"; ""))
      end;

  def storage_gb($label):
    if ($label | endswith("TB")) then (($label | sub("TB$"; "") | tonumber) * 1024)
    else ($label | sub(" ?GB$"; "") | tonumber)
    end;

  def source_rows:
    (
      [$rejoy[0].iphone_models[] | {
        source: "Rejoy",
        source_label: .source_label,
        canonical_model_key: canonical_key(.source_label),
        storages: .storage_options_gb
      }]
      + [$showme[0].iphone_models[] | {
        source: "ShowMe",
        source_label: .source_model_label,
        canonical_model_key: canonical_key(.source_model_label),
        storages: .storage_options_gb
      }]
      + [$norbi[0].iphone_models[] | {
        source: "NorbiPhone",
        source_label: .source_label,
        canonical_model_key: canonical_key(.source_label),
        storages: [.storage_options[] | storage_gb(.)]
      }]
    );

  ($inventory[0] | map({key, name, year})) as $catalog
  | (source_rows) as $source_rows
  | (($catalog | map(.key)) + ($source_rows | map(.canonical_model_key)) | unique | sort) as $keys
  | {
      schema_version: "1.0.0",
      generated_at: "2026-07-16T00:00:00Z",
      methodology: "Union of the live Apple Klinika iPhone inventory catalog and three public competitor calculator matrices; storage is source-observed only.",
      inventory_model_count: ($catalog | length),
      source_model_counts: {
        Rejoy: ($rejoy[0].iphone_models | length),
        ShowMe: ($showme[0].iphone_models | length),
        NorbiPhone: ($norbi[0].iphone_models | length)
      },
      models: [
        $keys[] as $key
        | ($catalog | map(select(.key == $key)) | first) as $catalog_item
        | ($source_rows | map(select(.canonical_model_key == $key))) as $matches
        | {
            canonical_model_key: $key,
            appleklinika_inventory_label: ($catalog_item.name // null),
            official_display_label: ($catalog_item.name // ($matches[0].source_label)),
            release_year: ($catalog_item.year // null),
            storage_variants_gb: ([$matches[].storages[]] | unique | sort),
            source_availability: {
              Rejoy: ([$matches[] | select(.source == "Rejoy")] | length > 0),
              ShowMe: ([$matches[] | select(.source == "ShowMe")] | length > 0),
              NorbiPhone: ([$matches[] | select(.source == "NorbiPhone")] | length > 0)
            },
            source_aliases: {
              Rejoy: ([$matches[] | select(.source == "Rejoy") | .source_label] | unique),
              ShowMe: ([$matches[] | select(.source == "ShowMe") | .source_label] | unique),
              NorbiPhone: ([$matches[] | select(.source == "NorbiPhone") | .source_label] | unique)
            },
            unsupported_source_aliases: [],
            inventory_model_missing: ($catalog_item == null),
            mapping_confidence: (
              if $catalog_item == null then "medium"
              elif $key == "iphone_air" then "medium"
              else "high"
              end
            ),
            price_benchmark_availability: (
              if $key == "iphone_13_pro" then {
                qualified_storage_variants_gb: [128],
                status: "partial_two_source_reference_only"
              } else {
                qualified_storage_variants_gb: [],
                status: "insufficient_benchmark_evidence"
              } end
            ),
            manual_mapping_issue_codes: (
              if $catalog_item == null then ["inventory_model_missing"]
              elif $key == "iphone_air" then ["source_alias_normalized"]
              elif $key == "iphone_se_2nd_generation" or $key == "iphone_se_3rd_generation" then ["source_generation_alias_normalized"]
              else []
              end
            )
          }
      ]
    }
  | .canonical_model_count = (.models | length)
  | .canonical_model_storage_combination_count = ([.models[].storage_variants_gb[]] | length)
  | .inventory_gaps = [.models[] | select(.inventory_model_missing) | {canonical_model_key, official_display_label, issue_code:"inventory_model_missing"}]
  ' > docs/research/buyback/iphone-model-variant-matrix-2026-07-16.json

jq empty docs/research/buyback/iphone-model-variant-matrix-2026-07-16.json
