(function () {
  'use strict';

  const root = document.querySelector('.ak-buyback-demo');
  if (!root) return;

  const form = root.querySelector('[data-demo-form]');
  const panels = Array.from(root.querySelectorAll('[data-demo-panel]'));
  const globalBack = root.querySelector('.ak-buyback-demo__navigation [data-demo-back]');
  const crumb = root.querySelector('[data-demo-crumb]');
  const progress = root.querySelector('[data-demo-progress]');
  const progressText = root.querySelector('[data-demo-progress-text]');
  const progressBar = progress ? progress.parentElement : null;
  const storefrontLogo = document.querySelector('header .ak-header-logo');
  const visualPanels = root.querySelectorAll('[data-demo-visual]');
  const modelCards = Array.from(root.querySelectorAll('[data-model-card]'));
  const modelGrid = root.querySelector('[data-model-grid]');
  const modelNoResults = root.querySelector('[data-model-no-results]');
  const storageCards = Array.from(root.querySelectorAll('[data-storage-card]'));
  const colorPicker = root.querySelector('[data-color-picker]');
  const colorOptions = root.querySelector('[data-color-options]');
  const networkModal = root.querySelector('[data-network-modal]');
  const serviceHistoryModal = root.querySelector('[data-service-history-modal]');
  const serviceHistoryOpen = root.querySelector('[data-service-history-open]');
  const networkInputs = Array.from(root.querySelectorAll('input[name="questionnaire[network_status]"]'));
  const modeInputs = Array.from(root.querySelectorAll('[data-mode-input]'));
  const offerContinue = root.querySelector('[data-offer-continue]');
  const offerSummarySelection = root.querySelector('[data-offer-summary-selection]');
  const modeMessage = root.querySelector('[data-mode-message]');
  const finalMessage = root.querySelector('[data-final-message]');
  const reviewModeTitle = root.querySelector('[data-review-mode-title]');
  const reviewModeHeadline = root.querySelector('[data-review-mode-headline]');
  const reviewModeDescription = root.querySelector('[data-review-mode-description]');
  const reviewModeProcess = root.querySelector('[data-review-mode-process]');
  const submissionMode = root.querySelector('[data-selected-offer-mode]');
  const publicSubmit = root.querySelector('[data-public-submit]');
  const publicSubmitMessage = root.querySelector('[data-public-submit-message]');
  const warnedVisualStates = new Set();
  let visualCatalogue = { assets: {}, fallback: {} };

  try {
    const catalogue = JSON.parse(root.dataset.visualCatalogue || '{}');
    if (catalogue && catalogue.assets && catalogue.fallback) visualCatalogue = catalogue;
  } catch (error) {
    console.warn('[Apple Klinika Buyback] A vizuális állapotkatalógus nem olvasható; a semleges illusztráció marad aktív.');
  }

  let flow = panels.map((panel) => panel.dataset.demoPanel);
  try {
    const configuredFlow = JSON.parse(root.dataset.panelOrder || '[]');
    if (Array.isArray(configuredFlow) && configuredFlow.length > 0) {
      flow = configuredFlow;
    }
  } catch (error) {
    // The rendered panel order is only a convenience; DOM order remains a safe fallback.
  }

  let current = root.dataset.initialPanel || 'entry';

  const panelFor = (name) => panels.find((panel) => panel.dataset.demoPanel === name);
  const selectedModel = () => root.querySelector('input[name="model_key"]:checked');
  const selectedStorage = () => root.querySelector('input[name="storage_gb"]:checked');

  function syncModelProgression() {
    const button = panelFor('model')?.querySelector('[data-demo-next]');
    if (button) button.disabled = !selectedModel();
  }

  function syncSingleChoiceDescriptions(group) {
    if (!group || group.dataset.questionType !== 'single') return;
    group.querySelectorAll('input[type="radio"]').forEach((input) => {
      const card = input.closest('.ak-buyback-demo__choice-card');
      const description = card?.querySelector('[data-choice-description], .ak-buyback-demo__choice-description');
      if (!description) return;
      const expanded = input.checked;
      description.hidden = !expanded;
      input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if (expanded) {
        input.setAttribute('aria-describedby', description.id);
      } else {
        input.removeAttribute('aria-describedby');
      }
    });
  }

  function syncWizardViewport() {
    const main = root.closest('main');
    if (!main) return;
    const availableHeight = Math.max(0, Math.floor(window.innerHeight - main.getBoundingClientRect().top));
    root.style.setProperty('--ak-buyback-available-height', availableHeight + 'px');
  }

  function syncStorefrontHeader(contained) {
    if (!storefrontLogo) return;
    if (!storefrontLogo.dataset.buybackOriginalLabel) {
      storefrontLogo.dataset.buybackOriginalLabel = storefrontLogo.getAttribute('aria-label') || '';
    }
    storefrontLogo.setAttribute('aria-label', contained ? 'Vissza a webshopba' : storefrontLogo.dataset.buybackOriginalLabel);
  }

  function syncWizardPageState() {
    const contained = !['entry', 'offers', 'review'].includes(current);
    const compactHeader = !['entry', 'review'].includes(current);
    document.body.classList.toggle('ak-buyback-entry-active', current === 'entry');
    document.body.classList.toggle('ak-buyback-wizard-active', contained);
    document.body.classList.toggle('ak-buyback-offer-active', current === 'offers');
    syncStorefrontHeader(compactHeader);
    if (contained) {
      window.scrollTo(0, 0);
      syncWizardViewport();
    }
  }

  function selectedModelCard() {
    const input = selectedModel();
    return input ? input.closest('[data-model-card]') : null;
  }

  function updateDeviceContext() {
    const card = selectedModelCard();
    const storage = selectedStorage();
    const label = card ? card.dataset.label : '';
    const storageLabel = storage ? (storage.value === '1024' ? '1 TB' : storage.value + ' GB') : '';

    if (crumb) {
      crumb.textContent = label ? label + (storageLabel ? ' · ' + storageLabel : '') : 'Készülék kiválasztása';
    }
  }

  function visualEntry(key) {
    if (!key) return visualCatalogue.fallback;
    return visualCatalogue.assets[key] || visualCatalogue.fallback;
  }

  function warnForMissingVisual(entry, key) {
    if (entry.asset_exists || warnedVisualStates.has(key)) return;
    warnedVisualStates.add(key);
    console.warn('[Apple Klinika Buyback] Hiányzó végleges állapotillusztráció: ' + entry.expected_path + '. Az átmeneti Apple Klinika fallback látható.');
  }

  function preloadVisualGroup(key) {
    const group = key.split('/')[0];
    if (!group) return;
    Object.entries(visualCatalogue.assets).forEach(([assetKey, entry]) => {
      if (assetKey.split('/')[0] !== group || !entry.active_url || entry.preloaded) return;
      entry.preloaded = true;
      const image = new Image();
      image.src = entry.active_url;
    });
  }

  function applyVisual(entry, key) {
    visualPanels.forEach((visual) => {
      const image = visual.querySelector('[data-demo-device-image]');
      const label = visual.querySelector('[data-demo-visual-label]');
      const description = visual.querySelector('[data-demo-visual-description]');
      if (!image) return;
      visual.dataset.visualKey = key || entry.visual_key || 'device/fallback';
      visual.dataset.visualView = entry.view_type || 'front';
      if (label) label.textContent = entry.answer_label || 'Kiválasztott iPhone';
      if (description) description.textContent = entry.question_label || 'A készülék állapotának szemléltetése';
      image.alt = entry.alt || 'iPhone állapotillusztráció';
      image.onerror = () => {
        if (image.src !== entry.fallback_url) {
          console.warn('[Apple Klinika Buyback] Az állapotillusztráció nem tölthető be; a fallback jelenik meg.');
          image.src = entry.fallback_url;
        }
      };
      if (image.src !== entry.active_url) {
        visual.classList.add('is-transitioning');
        image.src = entry.active_url;
        window.setTimeout(() => visual.classList.remove('is-transitioning'), 180);
      }
    });
    warnForMissingVisual(entry, key || entry.visual_key || 'device/fallback');
  }

  function updateConditionVisual(input) {
    const choice = input && input.closest('[data-visual-key]');
    const key = choice ? choice.dataset.visualKey : '';
    if (!key) return;
    applyVisual(visualEntry(key), key);
    preloadVisualGroup(key);
  }

  function syncVisualForPanel(panel) {
    const selected = panel ? panel.querySelector('[data-visual-key]:not([data-visual-key=""]) input:checked') : null;
    if (selected) {
      updateConditionVisual(selected);
      return;
    }
    applyVisual(visualEntry(''), '');
  }

  function updateStorageAvailability() {
    const card = selectedModelCard();
    const allowed = card && card.dataset.storages ? card.dataset.storages.split(',') : [];

    storageCards.forEach((storageCard) => {
      const input = storageCard.querySelector('input');
      const available = allowed.includes(storageCard.dataset.storage);
      storageCard.hidden = !available;
      input.disabled = !available;
      if (!available && input.checked) input.checked = false;
    });

    updateDeviceContext();
    updateColorOptions();
  }

  function updateColorOptions() {
    if (!colorPicker || !colorOptions) return;
    const card = selectedModelCard();
    const storage = selectedStorage();
    let colors = {};
    try { colors = card && storage ? (JSON.parse(card.dataset.colors || '{}')[storage.value] || {}) : {}; } catch (error) { colors = {}; }
    const current = root.querySelector('input[name="color_key"]:checked');
    const selected = current ? current.value : (colorPicker.dataset.currentColor || '');
    colorOptions.innerHTML = '';
    Object.entries(colors).forEach(([key, label]) => {
      const id = 'ak-demo-color-' + key;
      const card = document.createElement('label');
      card.className = 'ak-buyback-demo__choice-card ak-buyback-demo__choice-card--compact';
      card.innerHTML = '<input id="' + id + '" type="radio" name="color_key" value="' + key + '"' + (key === selected ? ' checked' : '') + '><strong>' + label + '</strong>';
      colorOptions.appendChild(card);
    });
    colorPicker.hidden = Object.keys(colors).length === 0;
  }

  function openNetworkModal() {
    if (!networkModal) return;
    networkModal.hidden = false;
    const close = networkModal.querySelector('[data-network-close]');
    if (close) close.focus({ preventScroll: true });
  }

  function closeNetworkModal() {
    if (!networkModal) return;
    networkModal.hidden = true;
    const locked = root.querySelector('input[name="questionnaire[network_status]"][value="locked"]');
    if (locked) locked.focus({ preventScroll: true });
  }

  function openServiceHistoryModal() {
    if (!serviceHistoryModal) return;
    serviceHistoryModal.hidden = false;
    const close = serviceHistoryModal.querySelector('[data-service-history-close]');
    if (close) close.focus({ preventScroll: true });
  }

  function closeServiceHistoryModal() {
    if (!serviceHistoryModal) return;
    serviceHistoryModal.hidden = true;
    if (serviceHistoryOpen) serviceHistoryOpen.focus({ preventScroll: true });
  }

  function updateConditionalQuestions() {
    const serviceHistory = root.querySelector('input[name="questionnaire[service_history]"]:checked');
    root.querySelectorAll('[data-conditional-on="service_history"]').forEach((group) => {
      const visible = serviceHistory && serviceHistory.value !== group.dataset.conditionalExcept;
      group.hidden = !visible;
      group.querySelectorAll('input').forEach((input) => { input.disabled = !visible; if (!visible) input.checked = false; });
      if (!visible) clearQuestionError(group);
    });
  }

  function selectedOfferCard() {
    const input = root.querySelector('[data-mode-input]:checked');
    return input ? input.closest('[data-mode-card]') : null;
  }

  function updateOfferReview(card) {
    if (offerSummarySelection) {
      offerSummarySelection.textContent = card
        ? [card.dataset.modeTitle, card.dataset.modeHeadline].filter(Boolean).join(' · ')
        : 'Még nincs kiválasztva.';
    }
    if (!card) return;
    if (reviewModeTitle) reviewModeTitle.textContent = card.dataset.modeTitle || '';
    if (reviewModeHeadline) reviewModeHeadline.textContent = card.dataset.modeHeadline || '—';
    if (reviewModeDescription) reviewModeDescription.textContent = card.dataset.modeDescription || '';
    if (reviewModeProcess) reviewModeProcess.textContent = card.dataset.modeProcess || '';
  }

  function selectOffer(card) {
    if (!card) return;
    const input = card.querySelector('[data-mode-input]');
    if (input) input.checked = true;
    root.querySelectorAll('[data-mode-card]').forEach((item) => item.classList.toggle('is-selected', item === card));
    if (offerContinue) offerContinue.disabled = false;
    if (submissionMode && input) submissionMode.value = input.value;
    if (publicSubmit) publicSubmit.disabled = false;
    if (publicSubmitMessage) publicSubmitMessage.hidden = true;
    if (modeMessage) modeMessage.hidden = false;
    updateOfferReview(card);
  }

  function updateProgress(name) {
    const index = Math.max(0, flow.indexOf(name));
    const max = Math.max(1, flow.length - 1);
    const percent = name === 'entry' ? 0 : Math.round((index / max) * 100);

    if (progress) progress.style.width = percent + '%';
    if (progressText) progressText.textContent = percent + '%';
    if (progressBar) progressBar.setAttribute('aria-valuenow', String(percent));
    if (globalBack) globalBack.hidden = name === 'entry';
  }

  function show(name, focusHeading) {
    const target = panelFor(name);
    if (!target) return;

    panels.forEach((panel) => { panel.hidden = panel !== target; });
    current = name;
    syncWizardPageState();
    updateProgress(name);
    updateDeviceContext();
    syncVisualForPanel(target);

    if (focusHeading) {
      const heading = target.querySelector('h3');
      if (heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus({ preventScroll: true });
      }
      if (!document.body.classList.contains('ak-buyback-wizard-active') || window.matchMedia('(max-width: 900px)').matches) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }

  function clearQuestionError(group) {
    group.classList.remove('has-error');
    group.removeAttribute('aria-invalid');
    const error = group.querySelector('[data-question-error]');
    if (error) error.remove();
  }

  function showQuestionError(group, message) {
    clearQuestionError(group);
    group.classList.add('has-error');
    group.setAttribute('aria-invalid', 'true');
    const error = document.createElement('p');
    error.className = 'ak-buyback-demo__question-error';
    error.dataset.questionError = '';
    error.textContent = message;
    group.appendChild(error);
    const input = group.querySelector('input');
    if (input) input.focus({ preventScroll: true });
  }

  function validateQuestion(group) {
    if (group.hidden) return true;
    clearQuestionError(group);
    const type = group.dataset.questionType;

    if (type === 'range') {
      const input = group.querySelector('[data-battery-number]');
      if (!input || !input.checkValidity()) {
        if (input) input.reportValidity();
        return false;
      }
      return true;
    }

    const checked = group.querySelectorAll('input:checked');
    if (checked.length === 0) {
      showQuestionError(group, 'Kérjük, válassz legalább egy lehetőséget.');
      return false;
    }

    return true;
  }

  function validateCurrentPanel() {
    const panel = panelFor(current);
    if (!panel) return false;

    if (current === 'model') {
      const selected = selectedModel();
      if (!selected) {
        const candidate = panel.querySelector('input[name="model_key"]');
        if (candidate) {
          candidate.setCustomValidity('Kérjük, válassz egy iPhone modellt.');
          candidate.reportValidity();
          candidate.setCustomValidity('');
        }
        return false;
      }
    }

    if (current === 'configuration') {
      const selected = selectedStorage();
      if (!selected) {
        const candidate = panel.querySelector('input[name="storage_gb"]:not(:disabled)');
        if (candidate) {
          candidate.setCustomValidity('Kérjük, válassz tárhelyet.');
          candidate.reportValidity();
          candidate.setCustomValidity('');
        }
        return false;
      }

      const network = panel.querySelector('input[name="questionnaire[network_status]"]:checked');
      if (!network) return false;
      if (network.value === 'locked') {
        openNetworkModal();
        return false;
      }
    }

    const manualReviewRoute = panel.querySelector('[data-manual-review-route]');
    if (current === 'offers' && !manualReviewRoute && !selectedOfferCard()) {
      if (modeMessage) {
        modeMessage.textContent = 'Válassz egy ajánlattípust a folytatáshoz.';
        modeMessage.hidden = false;
      }
      const firstMode = panel.querySelector('[data-mode-input]');
      if (firstMode) firstMode.focus({ preventScroll: true });
      return false;
    }

    const groups = Array.from(panel.querySelectorAll('[data-question]'));
    return groups.every(validateQuestion);
  }

  function validateAllQuestions() {
    let firstInvalid = null;
    root.querySelectorAll('[data-question]').forEach((group) => {
      if (!validateQuestion(group) && !firstInvalid) firstInvalid = group;
    });

    if (firstInvalid) {
      const panel = firstInvalid.closest('[data-demo-panel]');
      if (panel) show(panel.dataset.demoPanel, true);
      return false;
    }

    return true;
  }

  function nextName(button) {
    if (button.dataset.demoTarget) return button.dataset.demoTarget;
    const index = flow.indexOf(current);
    return flow[index + 1] || current;
  }

  root.addEventListener('click', (event) => {
    const next = event.target.closest('[data-demo-next]');
    if (next) {
      if (next.matches('[data-entry-family]')) next.setAttribute('aria-pressed', 'true');
      if (!validateCurrentPanel()) return;
      show(nextName(next), true);
      return;
    }

    const back = event.target.closest('[data-demo-back]');
    if (back) {
      const target = back.dataset.demoTarget;
      const index = flow.indexOf(current);
      show(target || flow[Math.max(0, index - 1)] || 'entry', true);
      return;
    }

    const networkClose = event.target.closest('[data-network-close]');
    if (networkClose) {
      closeNetworkModal();
      return;
    }

    if (event.target.closest('[data-service-history-open]')) {
      openServiceHistoryModal();
      return;
    }

    if (event.target.closest('[data-service-history-close]')) {
      closeServiceHistoryModal();
      return;
    }

    const mode = event.target.closest('[data-mode-select]');
    if (mode) {
      selectOffer(mode.closest('[data-mode-card]'));
      return;
    }

    const finalCta = event.target.closest('[data-demo-final-cta]');
    if (finalCta && finalMessage) {
      finalMessage.hidden = false;
    }
  });

  modelCards.forEach((card) => {
    const input = card.querySelector('input');
    input.addEventListener('change', () => {
      updateStorageAvailability();
      syncModelProgression();
    });
  });

  root.querySelectorAll('input[name="storage_gb"]').forEach((input) => input.addEventListener('change', updateDeviceContext));
  root.addEventListener('change', (event) => {
    if (event.target.name === 'storage_gb' || event.target.name === 'model_key') {
      updateColorOptions();
    }
  });

  networkInputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (input.checked && input.value === 'locked') openNetworkModal();
    });
  });

  root.querySelectorAll('input[name="questionnaire[service_history]"]').forEach((input) => {
    input.addEventListener('change', updateConditionalQuestions);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && serviceHistoryModal && !serviceHistoryModal.hidden) {
      event.preventDefault();
      closeServiceHistoryModal();
    }
  });

  modeInputs.forEach((input) => {
    input.addEventListener('change', () => {
      if (input.checked) selectOffer(input.closest('[data-mode-card]'));
    });
  });

  const search = root.querySelector('[data-model-search]');
  if (search) {
    search.addEventListener('input', () => {
      const query = search.value.trim().toLocaleLowerCase('hu-HU');
      let visibleCount = 0;
      modelCards.forEach((card) => {
        const visible = card.dataset.searchText.includes(query);
        card.hidden = !visible;
        if (visible) visibleCount += 1;
      });
      if (modelNoResults) modelNoResults.hidden = visibleCount !== 0;
      if (modelGrid) modelGrid.scrollTop = 0;
    });
  }

  const range = root.querySelector('[data-battery-range]');
  const number = root.querySelector('[data-battery-number]');
  const output = root.querySelector('[data-battery-output]');

  function syncBattery(source) {
    if (!range || !number || !output) return;
    const minimum = Number(number.min || 70);
    const maximum = Number(number.max || 100);
    const value = Math.max(minimum, Math.min(maximum, Number(source.value) || minimum));
    range.value = String(value);
    number.value = String(value);
    output.textContent = value + '%';
    output.setAttribute('aria-label', 'Akkumulátor állapota ' + value + ' százalék');
  }

  if (range && number && output) {
    range.addEventListener('input', () => syncBattery(range));
    number.addEventListener('input', () => syncBattery(number));
    syncBattery(number);
  }

  root.querySelectorAll('[data-question-type="multi"]').forEach((group) => {
    const exclusiveValue = group.dataset.exclusiveValue;
    const inputs = Array.from(group.querySelectorAll('input[type="checkbox"]'));
    const exclusive = inputs.find((input) => input.value === exclusiveValue);

    function normalizeMulti(changed) {
      if (changed && changed === exclusive && exclusive.checked) {
        inputs.forEach((input) => {
          if (input !== exclusive) input.checked = false;
        });
      } else if (changed && changed !== exclusive && changed.checked && exclusive) {
        exclusive.checked = false;
      }

      if (!inputs.some((input) => input.checked) && exclusive) {
        exclusive.checked = true;
      }
      clearQuestionError(group);
    }

    inputs.forEach((input) => input.addEventListener('change', () => normalizeMulti(input)));
    normalizeMulti(null);
  });

  root.querySelectorAll('[data-question] input').forEach((input) => {
    input.addEventListener('change', () => {
      const group = input.closest('[data-question]');
      if (group) {
        clearQuestionError(group);
        syncSingleChoiceDescriptions(group);
      }
      updateConditionVisual(input);
    });
  });

  if (form) {
    form.addEventListener('submit', (event) => {
      const validQuestions = validateAllQuestions();
      if (!validQuestions || !form.checkValidity()) {
        event.preventDefault();
        if (!form.checkValidity()) form.reportValidity();
      }
    });
  }

  updateStorageAvailability();
  syncModelProgression();
  root.querySelectorAll('[data-question-type="single"]').forEach(syncSingleChoiceDescriptions);
  updateConditionalQuestions();
  const existingOffer = selectedOfferCard();
  if (existingOffer) selectOffer(existingOffer);
  show(current, false);
  window.addEventListener('resize', syncWizardViewport);
  window.addEventListener('pageshow', () => syncVisualForPanel(panelFor(current)));
  window.addEventListener('popstate', () => syncVisualForPanel(panelFor(current)));
}());
