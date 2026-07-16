#!/usr/bin/env zsh
set -euo pipefail

out=/tmp/norbiphone-standard-observations.jsonl
unsupported=/tmp/norbiphone-unsupported-combinations.jsonl
: > "$out"
: > "$unsupported"
count=0
consecutive_errors=0

while IFS=$'\t' read -r model storage; do
  payload=$(jq -nc \
    --arg model "$model" \
    --arg storage "$storage" \
    '{brand:"iPhone",model:$model,storage:$storage,unlock:"igen",screen:"hibatlan",frame:"hibatlan",back:"hibatlan",burn:"hibatlan",akku:90,aftermarket:false,defect_count:0}')

  response_file=/tmp/norbiphone-calculator-response.json
  http_status=$(curl -s -o "$response_file" -w '%{http_code}' -X POST \
    -H 'Content-Type: application/json' \
    --data "$payload" \
    https://api.norbiphone.hu/api/calculator/calculate)
  response=$(cat "$response_file")

  if [[ "$http_status" == "403" || "$http_status" == "429" ]]; then
    printf 'ACCESS_STOP\t%s\t%s\tHTTP %s\n' "$model" "$storage" "$http_status"
    exit 43
  fi

  if [[ "$http_status" == "404" ]]; then
    jq -nc \
      --arg model "$model" \
      --arg storage "$storage" \
      --arg observed_at "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
      '{source:"NorbiPhone",source_model_label:$model,storage_label:$storage,status:"unsupported_or_unpriced",http_status:404,observed_at:$observed_at}' \
      >> "$unsupported"
    count=$((count + 1))
    consecutive_errors=0
    printf 'UNSUPPORTED %d/76 %s %s\n' "$count" "$model" "$storage"
    sleep 1
    continue
  fi

  if [[ "$http_status" != "200" ]] || ! printf '%s' "$response" | jq -e '(.cash|type)=="number" and (.fiokpenz|type)=="number"' >/dev/null 2>&1; then
    consecutive_errors=$((consecutive_errors + 1))
    printf 'ERROR %d\t%s\t%s\tHTTP %s\t%s\n' "$consecutive_errors" "$model" "$storage" "$http_status" "$response"
    if (( consecutive_errors >= 2 )); then
      exit 42
    fi
    count=$((count + 1))
    sleep 1
    continue
  fi

  consecutive_errors=0

  observed_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  jq -nc \
    --arg source "NorbiPhone" \
    --arg model "$model" \
    --arg storage "$storage" \
    --arg observed_at "$observed_at" \
    --argjson result "$response" \
    '{source:$source,source_model_label:$model,storage_label:$storage,standardized_scenario_id:"iphone_reference_v1",questionnaire_answers:{network_lock:false,liquid_damage:false,screen_condition:"perfect",screen_functional:true,frame_condition:"perfect",back_glass_condition:"perfect",battery_health:90,replacement_battery:false,other_defect_count:0},payout_modes:[{source_payout_mode:"cash",amount_minor:($result.cash*100),currency:"HUF",preliminary:true},{source_payout_mode:"trade_in_credit",amount_minor:($result.fiokpenz*100),currency:"HUF",preliminary:true}],observed_at:$observed_at,evidence_method:"public_calculator_api_sequential",confidence:"high"}' \
    >> "$out"

  count=$((count + 1))
  printf 'RECORDED %d/76 %s %s\n' "$count" "$model" "$storage"
  sleep 1
done < /tmp/norbiphone-iphone-combos.tsv

jq -s '.' "$out" > /tmp/norbiphone-standard-observations.json
jq -s '.' "$unsupported" > /tmp/norbiphone-unsupported-combinations.json
printf 'COMPLETE observations=%s unsupported=%s\n' \
  "$(jq length /tmp/norbiphone-standard-observations.json)" \
  "$(jq length /tmp/norbiphone-unsupported-combinations.json)"
