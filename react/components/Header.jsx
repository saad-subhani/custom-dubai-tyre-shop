import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Menu, X, ShoppingBag, Phone, Zap, Facebook, Instagram, Linkedin } from 'lucide-react';

function Header({ config, navigation }) {
	const [isOpen, setIsOpen] = useState(false);
	const [cartCount, setCartCount] = useState(0);
	useEffect(() => {
		const refresh = () => {
			const count = document.querySelector('[data-cart-count], .cart-count, .woocommerce-cart-count');
			if (count) setCartCount(parseInt(count.textContent, 10) || 0);
		};
		refresh();
		document.addEventListener('wc_fragments_refreshed', refresh);
		document.addEventListener('added_to_cart', refresh);
		return () => {
			document.removeEventListener('wc_fragments_refreshed', refresh);
			document.removeEventListener('added_to_cart', refresh);
		};
	}, []);
	useEffect(() => {
		const onKey = (event) => event.key === 'Escape' && setIsOpen(false);
		document.addEventListener('keydown', onKey);
		return () => document.removeEventListener('keydown', onKey);
	}, []);
	const callLink = (number) => `tel:${number.replace(/[^+\d]/g, '')}`;
	const socials = [
		['Facebook', Facebook], ['Instagram', Instagram], ['LinkedIn', Linkedin],
	].filter(([name]) => config.social?.[name]);

	return <>
		<div className="dts-header__topbar"><Zap aria-hidden="true" /><span>Fast delivery and professional tyre fitting across the UAE</span><Zap aria-hidden="true" /></div>
		<header className="dts-header">
			<div className="dts-header__inner">
				<a className="dts-header__brand" href={config.homeUrl} aria-label={config.siteName}>
					<img src={config.logoUrl} alt={config.siteName} />
				</a>
				<div className="dts-header__actions">
					<button className="dts-header__cart" type="button" aria-label="Open shopping cart" onClick={() => document.dispatchEvent(new CustomEvent('custom-dubai:open-cart'))}>
						<ShoppingBag aria-hidden="true" />{cartCount > 0 && <span className="dts-header__cart-count">{cartCount}</span>}
					</button>
					<button className="dts-header__toggle" type="button" aria-label="Open site information" aria-expanded={isOpen} onClick={() => setIsOpen(true)}><Menu aria-hidden="true" /></button>
				</div>
			</div>
		</header>
		<div className={`dts-header-drawer__backdrop${isOpen ? ' is-open' : ''}`} onClick={() => setIsOpen(false)} aria-hidden="true" />
		<aside className={`dts-header-drawer${isOpen ? ' is-open' : ''}`} aria-label="Site information" aria-hidden={!isOpen}>
			<div className="dts-header-drawer__top"><img src={config.logoUrl} alt={config.siteName} /><button type="button" aria-label="Close menu" onClick={() => setIsOpen(false)}><X aria-hidden="true" /></button></div>
			<p className="dts-header-drawer__description">{config.description || `Welcome to ${config.siteName}. Find tyres, fitting and support across the UAE.`}</p>
			<h2>Call our team</h2>
			<div className="dts-header-drawer__numbers">{config.phoneNumbers.map((number) => <a key={number} href={callLink(number)}><Phone aria-hidden="true" />{number}</a>)}<a href={callLink(config.supportNumber)}><Phone aria-hidden="true" />{config.supportNumber}</a></div>
			{navigation && <div className="dts-header-drawer__nav" dangerouslySetInnerHTML={{ __html: navigation }} />}
			{socials.length > 0 && <><h2>Follow us</h2><div className="dts-header-drawer__socials">{socials.map(([name, Icon]) => <a key={name} href={config.social[name]} aria-label={name} target="_blank" rel="noreferrer"><Icon aria-hidden="true" /></a>)}</div></>}
		</aside>
	</>;
}

document.querySelectorAll('#dts-header-root').forEach((root) => {
	const config = JSON.parse(root.dataset.header || '{}');
	const source = document.getElementById('dts-primary-navigation-source');
	createRoot(root).render(<Header config={config} navigation={source?.innerHTML || ''} />);
});
