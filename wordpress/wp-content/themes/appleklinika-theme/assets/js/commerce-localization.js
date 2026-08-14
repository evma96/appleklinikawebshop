(function () {
  if (!window.wp || !window.wp.hooks || typeof window.wp.hooks.addFilter !== 'function') {
    return;
  }

  var commerceTranslations = {
    'Contact information': 'Kapcsolati adatok',
    'Email address': 'E-mail cím',
    'Shipping address': 'Szállítási cím',
    'Country/Region': 'Ország/régió',
    'Shipping options': 'Szállítási mód',
    'Payment options': 'Fizetési mód',
    'Place order': 'Megrendelés',
    'Place Order': 'Megrendelés',
    'Free!': 'Ingyenes',
    'Free': 'Ingyenes',
    'FREE': 'Ingyenes',
    'Select a %s': 'Válassz: %s',
    'Undo': 'Visszavonás',
    '%s removed.': '%s eltávolítva.'
  };

  function translateCommerceString(translation, text, domain) {
    if (domain !== 'woocommerce') {
      return translation;
    }

    return Object.prototype.hasOwnProperty.call(commerceTranslations, text)
      ? commerceTranslations[text]
      : translation;
  }

  function translateCommerceStringWithContext(translation, text, context, domain) {
    return translateCommerceString(translation, text, domain);
  }

  window.wp.hooks.addFilter(
    'i18n.gettext',
    'appleklinika/commerce-localization',
    translateCommerceString
  );
  window.wp.hooks.addFilter(
    'i18n.gettext_with_context',
    'appleklinika/commerce-localization-context',
    translateCommerceStringWithContext
  );
}());
