( function( blocks, i18n, element, components, editor ) {
    const { registerPaymentMethod } = wc.wcBlocksRegistry;
    // Use the localized data from PHP
    const lapinopaygateways = lapinopaygatewayData || [];
    lapinopaygateways.forEach( ( lapinopaygateway ) => {
        registerPaymentMethod({
            name: lapinopaygateway.id,
            label: lapinopaygateway.label,
            ariaLabel: lapinopaygateway.label,
            content: element.createElement(
                'div',
                { className: 'lapinopay-method-wrapper' },
                element.createElement( 
                    'div', 
                    { className: 'lapinopay-method-label' },
                    '' + lapinopaygateway.description 
                ),
                lapinopaygateway.icon_url ? element.createElement(
                    'img', 
                    { 
                        src: lapinopaygateway.icon_url,
                        alt: lapinopaygateway.label,
                        className: 'lapinopay-method-icon'
                    }
                ) : null
            ),
            edit: element.createElement(
                'div',
                { className: 'lapinopay-method-wrapper' },
                element.createElement( 
                    'div', 
                    { className: 'lapinopay-method-label' },
                    '' + lapinopaygateway.description 
                ),
                lapinopaygateway.icon_url ? element.createElement(
                    'img', 
                    { 
                        src: lapinopaygateway.icon_url,
                        alt: lapinopaygateway.label,
                        className: 'lapinopay-method-icon'
                    }
                ) : null
            ),
            canMakePayment: () => true,
        });
    });
} )(
    window.wp.blocks,
    window.wp.i18n,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor
);