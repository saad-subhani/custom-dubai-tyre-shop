import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';

function ProductCard({ product }) {
	const [quantity, setQuantity] = useState(4);
	const [added, setAdded] = useState(false);

	const {
		name,
		image,
		size,
		brand,
		year,
		origin,
		origin_flag,
		warranty,
		badge,
		price,
		regular_price,
		set_price,
		currency,
		in_stock,
		fitment_included,
	} = product;

	const formatPrice = (value) => {
		return new Intl.NumberFormat('en-AE', {
			style: 'currency',
			currency: currency || 'AED',
			minimumFractionDigits: 0,
		}).format(value).replace('AED', 'AED ');
	};

	const handleAddToCart = () => {
		setAdded(true);
		setTimeout(() => setAdded(false), 2000);

		document.dispatchEvent(new CustomEvent('custom-dubai:add-to-cart', {
			detail: { product, quantity },
		}));
	};

	return (
		<article className="custom-dubai-product-card">
			<div className="custom-dubai-product-card__image-wrapper">
				{brand && (
					<span className="custom-dubai-product-card__brand">
						{brand}
					</span>
				)}
				<img
					className="custom-dubai-product-card__image"
					src={image}
					alt={name}
					loading="lazy"
				/>
			</div>

			<div className="custom-dubai-product-card__body">
				<h3 className="custom-dubai-product-card__title">
					{name}
				</h3>

				<div className="custom-dubai-product-card__meta">
					{year && (
						<span className="custom-dubai-product-card__meta-item">
							<svg className="custom-dubai-product-card__meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
								<line x1="16" y1="2" x2="16" y2="6" />
								<line x1="8" y1="2" x2="8" y2="6" />
								<line x1="3" y1="10" x2="21" y2="10" />
							</svg>
							{year}
						</span>
					)}
					{origin && (
						<span className="custom-dubai-product-card__meta-item">
							{origin_flag ? (
								<span className="custom-dubai-product-card__flag" role="img" aria-label={origin}>{origin_flag}</span>
							) : (
								<svg className="custom-dubai-product-card__meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
									<circle cx="12" cy="12" r="10" />
									<line x1="2" y1="12" x2="22" y2="12" />
									<path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
								</svg>
							)}
							{origin}
						</span>
					)}
					{warranty && (
						<span className="custom-dubai-product-card__meta-item">
							<svg className="custom-dubai-product-card__meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
							</svg>
							{warranty}
						</span>
					)}
				</div>

				<div className="custom-dubai-product-card__row">
					{badge && (
						<span className="custom-dubai-product-card__badge">
							{badge}
						</span>
					)}
					{size && (
						<span className="custom-dubai-product-card__size">
							<svg className="custom-dubai-product-card__size-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<circle cx="12" cy="12" r="10" />
								<circle cx="12" cy="12" r="4" />
								<line x1="12" y1="2" x2="12" y2="6" />
								<line x1="12" y1="18" x2="12" y2="22" />
								<line x1="2" y1="12" x2="6" y2="12" />
								<line x1="18" y1="12" x2="22" y2="12" />
							</svg>
							{size}
						</span>
					)}
				</div>

				<div className="custom-dubai-product-card__pricing">
					<div className="custom-dubai-product-card__price-row">
						<span className="custom-dubai-product-card__price">
							{formatPrice(price)}
						</span>
						<span className="custom-dubai-product-card__price-unit">/tyre</span>
					</div>
					{set_price && (
						<div className="custom-dubai-product-card__set-price">
							Set of 4: <strong>{formatPrice(set_price)}</strong>
						</div>
					)}
				</div>

				<div className="custom-dubai-product-card__actions">
					<div className="custom-dubai-product-card__quantity">
						<button
							type="button"
							className="custom-dubai-product-card__qty-btn"
							aria-label="Decrease quantity"
							onClick={() => setQuantity((q) => Math.max(1, q - 1))}
						>
							−
						</button>
						<span className="custom-dubai-product-card__qty-value">{quantity}</span>
						<button
							type="button"
							className="custom-dubai-product-card__qty-btn"
							aria-label="Increase quantity"
							onClick={() => setQuantity((q) => q + 1)}
						>
							+
						</button>
					</div>
					<button
						type="button"
						className="custom-dubai-product-card__add-btn"
						onClick={handleAddToCart}
						disabled={!in_stock}
					>
						<svg className="custom-dubai-product-card__cart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
							<circle cx="9" cy="21" r="1" />
							<circle cx="20" cy="21" r="1" />
							<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
						</svg>
						{added ? 'Added!' : 'Add to Cart'}
					</button>
				</div>

				<div className="custom-dubai-product-card__footer">
					{in_stock && (
						<span className="custom-dubai-product-card__stock">
							<svg className="custom-dubai-product-card__stock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
								<polyline points="22 4 12 14.01 9 11.01" />
							</svg>
							In Stock
						</span>
					)}
					{fitment_included && (
						<span className="custom-dubai-product-card__fitment">
							<svg className="custom-dubai-product-card__fitment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
							</svg>
							Fitment Included
							<svg className="custom-dubai-product-card__info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<circle cx="12" cy="12" r="10" />
								<line x1="12" y1="16" x2="12" y2="12" />
								<line x1="12" y1="8" x2="12.01" y2="8" />
							</svg>
						</span>
					)}
				</div>
			</div>
		</article>
	);
}

function ProductCardMount() {
	const containers = document.querySelectorAll('[data-product]');
	containers.forEach((container) => {
		const product = JSON.parse(container.dataset.product || '{}');
		const root = createRoot(container);
		root.render(<ProductCard product={product} />);
	});
}

ProductCardMount();

export default ProductCard;
