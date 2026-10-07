import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ChevronDown, Tag, Headset, Truck, Facebook, Instagram, Linkedin } from 'lucide-react';

const config = JSON.parse(document.getElementById('dts-footer-root')?.dataset.footer || '{}');
const socialIcons = { Facebook, Instagram, LinkedIn: Linkedin };

function FooterColumn({ column, index, expanded, onToggle }) {
	return <section className={`dts-footer__column${column.links?.length > 10 ? ' dts-footer__column--sizes' : ''}`}>
		<button type="button" className="dts-footer__column-toggle" aria-expanded={expanded} aria-controls={`dts-footer-links-${index}`} onClick={onToggle}>
			<span>{column.title}</span><ChevronDown aria-hidden="true" />
		</button>
		<div className={`dts-footer__column-content${expanded ? ' is-expanded' : ''}`} id={`dts-footer-links-${index}`}>
			<ul>{(column.links || []).map((link) => <li key={`${link.label}-${link.url}`}><a href={link.url}>{link.label}</a></li>)}</ul>
			{column.more && <a className="dts-footer__more" href={column.more.url}>{column.more.label}<span aria-hidden="true"> →</span></a>}
		</div>
	</section>;
}

function Footer() {
	const [openColumn, setOpenColumn] = useState(null);
	const benefits = [
		{ Icon: Tag, first: 'Best Price', second: 'Guaranteed' },
		{ Icon: Headset, first: 'Qualified', second: 'Agents' },
		{ Icon: Truck, first: 'Fast', second: 'Shipping' },
		{ Icon: Truck, first: 'Mobile', second: 'Fitment' },
	];
	return <footer className="site-footer dts-footer">
		<div className="dts-footer__main"><div className="dts-footer__inner">
			<header className="dts-footer__intro"><h2>{config.introTitle}</h2><p>{config.introText}</p></header>
			<nav className="dts-footer__columns" aria-label="Footer navigation">
				{(config.columns || []).map((column, index) => <FooterColumn key={column.title} column={column} index={index} expanded={openColumn === index} onToggle={() => setOpenColumn(openColumn === index ? null : index)} />)}
			</nav>
		</div></div>
		<section className="dts-footer__benefits" aria-label="Shopping benefits"><div className="dts-footer__benefits-inner">
			{benefits.map(({ Icon, first, second }) => <div className="dts-footer__benefit" key={first}><span className="dts-footer__benefit-icon"><Icon aria-hidden="true" /></span><p>{first}<strong>{second}</strong></p></div>)}
		</div></section>
		<div className="dts-footer__bottom"><div className="dts-footer__bottom-inner">
			<p className="dts-footer__copyright">© {config.siteName} {config.year}. All Rights Reserved.</p>
			<nav className="dts-footer__legal" aria-label="Legal links">{(config.legal || []).map((link) => <a href={link.url} key={link.label}>{link.label}</a>)}<span>Secure Online Payments</span></nav>
			<div className="dts-footer__social">{Object.entries(config.social || {}).map(([network, url]) => { const Icon = socialIcons[network]; return Icon && <a href={url} aria-label={network} target="_blank" rel="noopener noreferrer" key={network}><Icon aria-hidden="true" /></a>; })}{config.whatsapp && <a className="dts-footer__whatsapp" href={`https://wa.me/${config.whatsapp}`}>Contact us on WhatsApp</a>}</div>
		</div></div>
	</footer>;
}

const mount = document.getElementById('dts-footer-root');
if (mount) createRoot(mount).render(<Footer />);
