import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ArrowLeft, ArrowRight } from 'lucide-react';

function ShopByCategory({ categories = [] }) {
	const trackRef = useRef(null);
	const [canGoBack, setCanGoBack] = useState(false);
	const [canGoForward, setCanGoForward] = useState(false);

	useEffect(() => {
		const track = trackRef.current;
		if (!track) return undefined;
		const updateControls = () => {
			setCanGoBack(track.scrollLeft > 1);
			setCanGoForward(track.scrollLeft + track.clientWidth < track.scrollWidth - 1);
		};
		updateControls();
		const observer = new ResizeObserver(updateControls);
		observer.observe(track);
		track.addEventListener('scroll', updateControls, { passive: true });
		return () => {
			observer.disconnect();
			track.removeEventListener('scroll', updateControls);
		};
	}, [categories.length]);

	const move = (direction) => {
		const track = trackRef.current;
		if (!track) return;
		const card = track.querySelector('.dts-category-card');
		const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
		track.scrollBy({ left: direction * ((card?.getBoundingClientRect().width || track.clientWidth) + gap), behavior: 'smooth' });
	};

	if (!categories.length) return null;

	return (
		<section className="dts-shop-categories" aria-labelledby="dts-shop-categories-title">
			<div className="dts-shop-categories__inner">
				<header className="dts-shop-categories__header">
					<div>
						<p className="dts-shop-categories__eyebrow">Shop by type</p>
						<h2 id="dts-shop-categories-title">Shop by Category</h2>
						<p className="dts-shop-categories__intro">Find the perfect fit for your lifestyle and budget.</p>
					</div>
					<div className="dts-shop-categories__controls">
						<a href={window.DTSShopCategories?.allCategoriesUrl || '#'}>View all categories <span aria-hidden="true">→</span></a>
						<button type="button" onClick={() => move(-1)} disabled={!canGoBack} aria-label="Previous categories"><ArrowLeft aria-hidden="true" /></button>
						<button type="button" onClick={() => move(1)} disabled={!canGoForward} aria-label="Next categories"><ArrowRight aria-hidden="true" /></button>
					</div>
				</header>
				<div className="dts-shop-categories__track" ref={trackRef} role="list" aria-label="Shop product categories">
					{categories.map((category) => (
						<a className="dts-category-card" href={category.url} key={category.id} role="listitem">
							<img
								src={category.image.url}
								srcSet={category.image.srcset || undefined}
								sizes="(max-width: 600px) 100vw, (max-width: 900px) 50vw, 20vw"
								alt={category.image.alt || category.name}
								loading="lazy"
								decoding="async"
								fetchPriority="low"
							/>
							<span className="dts-category-card__shade" aria-hidden="true" />
							<span className="dts-category-card__copy">
								<strong>{category.name}</strong>
								{category.description && <small>{category.description}</small>}
							</span>
						</a>
					))}
				</div>
			</div>
		</section>
	);
}

const mount = document.getElementById('dts-shop-categories-root');
if (mount && window.DTSShopCategories) {
	createRoot(mount).render(<ShopByCategory categories={window.DTSShopCategories.categories || []} />);
}

export default ShopByCategory;
