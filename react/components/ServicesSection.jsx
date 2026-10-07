import React from 'react';
import { createRoot } from 'react-dom/client';
import { Sparkles, Truck, CircleAlert, CircleDot, Droplets, ShieldCheck } from 'lucide-react';

const services = [
	{ title: 'Car Detailing', href: 'https://www.dubaityreshop.com/car-detailing/', image: 'https://www.dubaityreshop.com/wp-content/themes/dts-custom-theme/img/c1.jpeg', Icon: Sparkles },
	{ title: 'Car Recovery', href: 'https://www.dubaityreshop.com/car-recovery/', image: 'https://www.dubaityreshop.com/wp-content/uploads/2026/09/flat-bed-recovert.jpg', Icon: Truck },
	{ title: 'Emergency Tire Fixing', href: 'https://www.dubaityreshop.com/emergency-tire-fixing/', image: 'https://www.dubaityreshop.com/wp-content/uploads/2026/09/tire-fixing-1.jpg', Icon: CircleAlert },
	{ title: 'Rims Repair And Paint', href: 'https://www.dubaityreshop.com/rims-repair-and-paint/', image: 'https://www.dubaityreshop.com/wp-content/themes/dts-custom-theme/img/prot-1.png', Icon: CircleDot },
	{ title: 'Nano Ceramic Coating', href: 'https://www.dubaityreshop.com/nano-ceramic-coating/', image: 'https://www.dubaityreshop.com/wp-content/themes/custom-theme/img/prot-1.png', Icon: Droplets },
	{ title: 'Paint Protection Film', href: 'https://www.dubaityreshop.com/paint-protection-film/', image: 'https://www.dubaityreshop.com/wp-content/themes/custom-theme/img/prot-1.png', Icon: ShieldCheck },
];

function ServicesSection() {
	return (
		<section className="dts-services" aria-labelledby="dts-services-title">
			<div className="dts-services__pattern" aria-hidden="true" />
			<div className="dts-services__inner">
				<header className="dts-services__header">
					<p className="dts-services__eyebrow"><span /> Our Services <span /></p>
					<h2 id="dts-services-title">Complete Care <em>for Your Vehicle</em></h2>
					<p>From routine maintenance to premium protection, explore our professional car services to keep your vehicle in top condition.</p>
				</header>
				<div className="dts-services__grid">
					{services.map(({ title, href, image, Icon }) => (
						<a className="dts-service-card" href={href} key={title}>
							<img className="dts-service-card__image" src={image} alt="" loading="lazy" decoding="async" />
							<span className="dts-service-card__shade" />
							<span className="dts-service-card__content">
								<span className="dts-service-card__icon"><Icon aria-hidden="true" /></span>
								<strong>{title}</strong>
								<span className="dts-service-card__rule" />
							</span>
						</a>
					))}
				</div>
			</div>
		</section>
	);
}

const mount = document.getElementById('dts-services-root');
if (mount) createRoot(mount).render(<ServicesSection />);

export default ServicesSection;
