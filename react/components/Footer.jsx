import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
	Mail,
	Phone,
	Facebook,
	Instagram,
	Linkedin,
	MousePointerClick,
	MessageCircle,
	Check,
	ChevronDown
} from 'lucide-react';

const config = JSON.parse(document.getElementById('dts-footer-root')?.dataset.footer || '{}');

const WhatsAppIcon = () => (
	<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
		<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
	</svg>
);

const MastercardLogo = () => (
	<span className="dts-payment-badge dts-payment-badge--mastercard" title="Mastercard">
		<svg viewBox="0 0 38 24" width="38" height="24" aria-hidden="true">
			<rect width="38" height="24" rx="4" style={{ fill: 'var(--color-brand-payment-badge-bg)', stroke: 'var(--color-brand-payment-badge-border)' }} strokeWidth="1" />
			<circle cx="15" cy="12" r="7" style={{ fill: 'var(--color-brand-mastercard-red)' }} />
			<circle cx="23" cy="12" r="7" style={{ fill: 'var(--color-brand-mastercard-orange)' }} fillOpacity="0.86" />
		</svg>
	</span>
);

const VisaLogo = () => (
	<span className="dts-payment-badge dts-payment-badge--visa" title="Visa">
		<svg viewBox="0 0 38 24" width="38" height="24" aria-hidden="true">
			<rect width="38" height="24" rx="4" style={{ fill: 'var(--color-brand-payment-badge-bg)', stroke: 'var(--color-brand-payment-badge-border)' }} strokeWidth="1" />
			<text x="50%" y="58%" dominantBaseline="middle" textAnchor="middle" style={{ fill: 'var(--color-brand-visa-blue)', fontFamily: 'var(--font-family-brand-badge)' }} fontWeight="900" fontStyle="italic" fontSize="11" letterSpacing="0.05em">
				VISA
			</text>
		</svg>
	</span>
);

const TamaraLogo = () => (
	<span className="dts-payment-badge dts-payment-badge--tamara" title="Tamara">
		<svg viewBox="0 0 54 24" width="54" height="24" aria-hidden="true">
			<rect width="54" height="24" rx="4" style={{ fill: 'var(--color-brand-tamara-pink)' }} />
			<rect x="27" width="27" height="24" rx="0" style={{ fill: 'var(--color-brand-tamara-mint)' }} />
			<text x="50%" y="58%" dominantBaseline="middle" textAnchor="middle" style={{ fill: 'var(--color-brand-tamara-ink)', fontFamily: 'var(--font-family-brand-badge)' }} fontWeight="800" fontSize="10" letterSpacing="-0.02em">
				tamara
			</text>
		</svg>
	</span>
);

function Newsletter() {
	const [email, setEmail] = useState('');
	const [subscribed, setSubscribed] = useState(false);

	const handleSubmit = (e) => {
		e.preventDefault();
		if (email.trim() && email.includes('@')) {
			setSubscribed(true);
			setEmail('');
			setTimeout(() => setSubscribed(false), 4000);
		}
	};

	return (
		<div className="dts-newsletter-bar">
			<div className="dts-newsletter-bar__inner">
				<div className="dts-newsletter-bar__left">
					<div className="dts-newsletter-bar__icon-box">
						<Mail aria-hidden="true" />
					</div>
					<div className="dts-newsletter-bar__text">
						<h3 className="dts-newsletter-bar__title">{config.newsletter?.title || 'Get Special Offers And Saving'}</h3>
						<p className="dts-newsletter-bar__subtitle">{config.newsletter?.subtitle || 'Get All The Latest Information On Events, Sales And Offers.'}</p>
					</div>
				</div>

				{config.newsletterTyreImg && (
					<div className="dts-newsletter-bar__center-img">
						<img src={config.newsletterTyreImg} alt="Tyres Special Offers" />
					</div>
				)}

				<div className="dts-newsletter-bar__right">
					<form className="dts-newsletter-bar__form" onSubmit={handleSubmit}>
						<input
							type="email"
							className="dts-newsletter-bar__input"
							placeholder="Enter Your Email"
							value={email}
							onChange={(e) => setEmail(e.target.value)}
							required
							aria-label="Enter Your Email"
						/>
						<button type="submit" className="dts-newsletter-bar__button" aria-label="Subscribe to newsletter">
							<span>{subscribed ? 'SUBSCRIBED' : 'SUBSCRIBE'}</span>
							{subscribed ? <Check aria-hidden="true" /> : <MousePointerClick aria-hidden="true" />}
						</button>
					</form>
				</div>
			</div>
		</div>
	);
}

function FooterColumn({ column, index, expanded, onToggle }) {
	return (
		<div className="dts-footer__column">
			<button
				type="button"
				className="dts-footer__column-toggle"
				aria-expanded={expanded}
				onClick={onToggle}
			>
				<h4 className="dts-footer__column-title">{column.title}</h4>
				<ChevronDown className="dts-footer__column-chevron" aria-hidden="true" />
			</button>
			<ul className={`dts-footer__column-list ${expanded ? 'is-expanded' : ''}`}>
				{(column.links || []).map((link, i) => (
					<li key={`${link.label}-${i}`} className="dts-footer__column-item">
						<a href={link.url} className="dts-footer__column-link">
							<span className="dts-footer__dash">- </span>
							{link.label}
						</a>
					</li>
				))}
			</ul>
		</div>
	);
}

function Footer() {
	const [openColumn, setOpenColumn] = useState(null);

	const contact = config.contact || {};
	const phoneDisplay = contact.phone || '800 92552';
	const phoneTel = contact.phoneTel || '80092552';
	const whatsappNum = contact.whatsapp || '971556269517';
	const emailDisplay = contact.email || 'customercare@dubaityreshop.com';

	const socials = [
		{ name: 'Facebook', Icon: Facebook, url: config.social?.Facebook || '#' },
		{ name: 'LinkedIn', Icon: Linkedin, url: config.social?.LinkedIn || '#' },
		{ name: 'Instagram', Icon: Instagram, url: config.social?.Instagram || '#' },
	].filter((s) => s.url);

	return (
		<footer className="dts-footer">
			<Newsletter />

			<div className="dts-footer__main">
				<div className="dts-footer__main-inner">
					<div className="dts-footer__brand-col">
						<a href={config.homeUrl || '/'} className="dts-footer__logo" aria-label={config.siteName}>
							<img src={config.logoUrl} alt={config.siteName} />
						</a>

						<div className="dts-footer__contact-list">
							<a href={`tel:${phoneTel}`} className="dts-footer__contact-item">
								<span className="dts-footer__contact-icon">
									<Phone aria-hidden="true" />
								</span>
								<div className="dts-footer__contact-info">
									<span className="dts-footer__contact-label">Call</span>
									<span className="dts-footer__contact-value">{phoneDisplay}</span>
								</div>
							</a>

							<a
								href={`https://wa.me/${whatsappNum}`}
								target="_blank"
								rel="noopener noreferrer"
								className="dts-footer__contact-item"
							>
								<span className="dts-footer__contact-icon">
									<WhatsAppIcon />
								</span>
								<div className="dts-footer__contact-info">
									<span className="dts-footer__contact-label">Chat</span>
									<span className="dts-footer__contact-value">Here &amp; Now</span>
								</div>
							</a>

							<a href={`mailto:${emailDisplay}`} className="dts-footer__contact-item">
								<span className="dts-footer__contact-icon">
									<Mail aria-hidden="true" />
								</span>
								<div className="dts-footer__contact-info">
									<span className="dts-footer__contact-label">Email</span>
									<span className="dts-footer__contact-value">{emailDisplay}</span>
								</div>
							</a>
						</div>

						<div className="dts-footer__social-list">
							{socials.map(({ name, Icon, url }) => (
								<a
									key={name}
									href={url}
									aria-label={name}
									target="_blank"
									rel="noopener noreferrer"
									className="dts-footer__social-link"
								>
									<Icon aria-hidden="true" />
								</a>
							))}
						</div>
					</div>

					<div className="dts-footer__nav-cols">
						{(config.columns || []).map((column, index) => (
							<FooterColumn
								key={column.title}
								column={column}
								index={index}
								expanded={openColumn === index}
								onToggle={() => setOpenColumn(openColumn === index ? null : index)}
							/>
						))}

						<div className="dts-footer__payment-section">
							<h4 className="dts-footer__payment-title">Secure Online Payment:</h4>
							<div className="dts-footer__payment-badges">
								<MastercardLogo />
								<VisaLogo />
								<TamaraLogo />
							</div>
						</div>
					</div>
				</div>
			</div>

			<div className="dts-footer__bottom">
				<div className="dts-footer__bottom-inner">
					<div className="dts-footer__copyright">
						Copyright &copy; {config.year || '2026'} {config.siteName || 'Dubai Tyre Shop'}. All Rights Reserved.
					</div>

					<nav className="dts-footer__legal-nav" aria-label="Legal navigation">
						{(config.legal || []).map((link, i) => (
							<React.Fragment key={link.label}>
								<a href={link.url} className="dts-footer__legal-link">
									{link.label}
								</a>
								{i < (config.legal || []).length - 1 && <span className="dts-footer__legal-sep">|</span>}
							</React.Fragment>
						))}
					</nav>

					<div className="dts-footer__developer">
						{config.developerText || 'Developed By'}{' '}
						<strong>{config.developerCompany || 'Klever Tech Solutions'}</strong>
					</div>
				</div>
			</div>

			{/* Floating Quick Action Widget matching reference */}
			<aside className="dts-floating-actions" aria-label="Quick contact">
				<a
					href={`tel:${phoneTel}`}
					className="dts-floating-phone"
					aria-label="Call support"
					title="Call support"
				>
					<Phone aria-hidden="true" />
				</a>
				<a
					href={`https://wa.me/${whatsappNum}`}
					target="_blank"
					rel="noopener noreferrer"
					className="dts-floating-chat"
					aria-label="Chat now on WhatsApp"
				>
					<MessageCircle aria-hidden="true" />
					<span>Chat Now!</span>
				</a>
			</aside>
		</footer>
	);
}

const mount = document.getElementById('dts-footer-root');
if (mount) {
	createRoot(mount).render(<Footer />);
}
