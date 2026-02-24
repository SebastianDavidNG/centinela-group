const settings       = window.wc.wcSettings.getSetting('my_custom_gateway_data', {});
const label          = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('', 'wompi');
const Content        = () => {
	const pluginPath = '/wp-content/plugins/wompi-portal-de-pagos/assets/img/wompi-logo-principal.svg';
	const siteUrl    = window.location.origin;
	const imageUrl   = `${siteUrl}${pluginPath}`;

	return Object(window.wp.element.createElement)(
		'div',
		{className: 'wc-block-payment-method__content', style: {display: 'flex'}},
		Object(window.wp.element.createElement)(
			'p',
			{className: 'wc-block-payment-method__description', style: {fontWeight: 'bold'}},
			window.wp.i18n.__('Pagas por', 'wompi')
		),
		Object(window.wp.element.createElement)(
			'div',
			{
				className: 'wc-block-payment-method__image-container', style: {
					alignItems: 'center',
					height: '75px',
					width: '100px',
				}
			},
			Object(window.wp.element.createElement)(
				'img',
				{
					src: imageUrl,
					alt: label,
					style: {
						height: '100%',
						objectFit: 'contain',
						width: '100%'
					},
					className: "wc-block-payment-method__image"
				}
			)
		)
	);
};

const Block_Gateway = {
	name: 'wompi',
	label: label,
	content: Object(window.wp.element.createElement)(Content, null),
	edit: Object(window.wp.element.createElement)(Content, null),
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);