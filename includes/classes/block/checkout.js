(() => {
  const settings = window.wc.wcSettings.getSetting(
    "wc_gateway_pesepay_data",
    {}
  );
  const logoUrl = window.PesepayBlockData.icon;
  const badgeUrl = window.PesepayBlockData.badge;

  const Label = () => {
    const title = settings.title || "Pesepay";
    if (logoUrl) {
      return window.wp.element.createElement("img", {
        src: logoUrl,
        alt: window.wp.htmlEntities.decodeEntities(title),
        style: { height: "20px" },
      });
    }
    return window.wp.element.createElement(
      "span",
      null,
      window.wp.htmlEntities.decodeEntities(title)
    );
  };

  const Content = () => {
    const nodes = [];

    nodes.push(
      window.wp.element.createElement(
        "p",
        { key: "desc" },
        window.wp.htmlEntities.decodeEntities(settings.description || "")
      )
    );

    if (badgeUrl) {
      nodes.push(
        window.wp.element.createElement("img", {
          key: "badge",
          src: badgeUrl,
          alt: window.wp.htmlEntities.decodeEntities(
            settings.title || "Pesepay"
          ),
          style: { height: "150px", marginTop: "12px" },
        })
      );
    }

    return window.wp.element.createElement("div", null, ...nodes);
  };

  window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: "wc_gateway_pesepay",
    label: window.wp.element.createElement(Label, {}),
    content: window.wp.element.createElement(Content, {}),
    edit: window.wp.element.createElement(Content, {}),
    canMakePayment: () => true,
    ariaLabel: settings.title || "Pesepay",
    supports: { features: settings.supports || [] },
  });
})();
