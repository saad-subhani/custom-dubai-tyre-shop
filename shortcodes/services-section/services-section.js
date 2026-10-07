(function () {
	if (!window.wp || !window.wp.element) return;
	var h = window.wp.element.createElement;
	var services = [
		{ title: 'Car Detailing', href: 'https://www.dubaityreshop.com/car-detailing/', image: 'https://www.dubaityreshop.com/wp-content/themes/dts-custom-theme/img/c1.jpeg', icon: 'detail' },
		{ title: 'Car Recovery', href: 'https://www.dubaityreshop.com/car-recovery/', image: 'https://www.dubaityreshop.com/wp-content/uploads/2026/09/flat-bed-recovert.jpg', icon: 'recovery' },
		{ title: 'Emergency Tire Fixing', href: 'https://www.dubaityreshop.com/emergency-tire-fixing/', image: 'https://www.dubaityreshop.com/wp-content/uploads/2026/09/tire-fixing-1.jpg', icon: 'tire' },
		{ title: 'Rims Repair And Paint', href: 'https://www.dubaityreshop.com/rims-repair-and-paint/', image: 'https://www.dubaityreshop.com/wp-content/themes/dts-custom-theme/img/prot-1.png', icon: 'rim' },
		{ title: 'Nano Ceramic Coating', href: 'https://www.dubaityreshop.com/nano-ceramic-coating/', image: 'https://www.dubaityreshop.com/wp-content/themes/custom-theme/img/prot-1.png', icon: 'coating' },
		{ title: 'Paint Protection Film', href: 'https://www.dubaityreshop.com/paint-protection-film/', image: 'https://www.dubaityreshop.com/wp-content/themes/custom-theme/img/prot-1.png', icon: 'shield' }
	];
	var paths = {
		detail: [h('path', { d: 'm12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z' }), h('path', { d: 'm19 14 .9 2.1L22 17l-2.1.9L19 20l-.9-2.1L16 17l2.1-.9L19 14Z' }), h('path', { d: 'm5 14 .7 1.3L7 16l-1.3.7L5 18l-.7-1.3L3 16l1.3-.7L5 14Z' })],
		recovery: [h('path', { d: 'M3 7h11v10H3zM14 10h4l3 3v4h-7z' }), h('circle', { cx: '7', cy: '18', r: '2' }), h('circle', { cx: '18', cy: '18', r: '2' }), h('path', { d: 'M6 7V4h9M10 4l-2 3' })],
		tire: [h('circle', { cx: '12', cy: '12', r: '9' }), h('path', { d: 'M12 7v6m0 4h.01' })],
		rim: [h('circle', { cx: '12', cy: '12', r: '9' }), h('circle', { cx: '12', cy: '12', r: '3' }), h('path', { d: 'M12 3v6m0 6v6M3 12h6m6 0h6m-15.4-6.4 4.2 4.2m6.4 4.4 4.2 4.2m0-12.8-4.2 4.2m-6.4 4.4-4.2 4.2' })],
		coating: [h('path', { d: 'M12 3s6 6.6 6 11a6 6 0 0 1-12 0c0-4.4 6-11 6-11Z' }), h('path', { d: 'M9 15a3 3 0 0 0 3 3' })],
		shield: [h('path', { d: 'M12 3 20 6v5c0 5-3.4 8.3-8 10-4.6-1.7-8-5-8-10V6l8-3Z' }), h('path', { d: 'm8.5 12 2.2 2.2 4.8-5' })]
	};
	function Icon(props) { return h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: '1.6', strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': 'true' }, paths[props.name]); }
	function ServicesSection() {
		return h('section', { className: 'dts-services', 'aria-labelledby': 'dts-services-title' },
			h('div', { className: 'dts-services__pattern', 'aria-hidden': 'true' }),
			h('div', { className: 'dts-services__inner' },
				h('header', { className: 'dts-services__header' },
					h('p', { className: 'dts-services__eyebrow' }, h('span'), 'Our Services', h('span')),
					h('h2', { id: 'dts-services-title' }, 'Complete Care ', h('em', null, 'for Your Vehicle')),
					h('p', null, 'From routine maintenance to premium protection, explore our professional car services to keep your vehicle in top condition.')
				),
				h('div', { className: 'dts-services__grid' }, services.map(function (service) {
					return h('a', { className: 'dts-service-card', href: service.href, key: service.title },
						h('img', { className: 'dts-service-card__image', src: service.image, alt: '', loading: 'lazy', decoding: 'async' }),
						h('span', { className: 'dts-service-card__shade' }),
						h('span', { className: 'dts-service-card__content' },
							h('span', { className: 'dts-service-card__icon' }, h(Icon, { name: service.icon })),
							h('strong', null, service.title), h('span', { className: 'dts-service-card__rule' })
						)
					);
				}))
			)
		);
	}
	var mount = document.getElementById('dts-services-root');
	if (mount) {
		if (window.wp.element.createRoot) window.wp.element.createRoot(mount).render(h(ServicesSection));
		else if (window.wp.element.render) window.wp.element.render(h(ServicesSection), mount);
	}
})();
