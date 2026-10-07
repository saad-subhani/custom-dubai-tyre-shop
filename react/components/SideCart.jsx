import React, { useState, useEffect, useCallback } from 'react';
import { createRoot } from 'react-dom/client';

function SideCart() {
	const [isOpen, setIsOpen] = useState(false);
	const [items, setItems] = useState([]);
	const [subtotal, setSubtotal] = useState(0);
	const [vat, setVat] = useState(0);
	const [total, setTotal] = useState(0);
	const [promoCode, setPromoCode] = useState('');
	const [promoApplied, setPromoApplied] = useState(false);

	const formatPrice = (value) => {
		return new Intl.NumberFormat('en-AE', {
			style: 'currency',
			currency: 'AED',
			minimumFractionDigits: 0,
		}).format(value).replace('AED', 'AED ');
	};

	const openCart = useCallback(() => setIsOpen(true), []);
	const closeCart = useCallback(() => setIsOpen(false), []);

	const updateQuantity = (index, delta) => {
		setItems((prev) => {
			const updated = [...prev];
			const newQty = updated[index].quantity + delta;
			if (newQty < 1) return prev;
			updated[index] = { ...updated[index], quantity: newQty };
			return updated;
		});
	};

	const removeItem = (index) => {
		setItems((prev) => prev.filter((_, i) => i !== index));
	};

	const applyPromo = () => {
		if (promoCode.trim()) {
			setPromoApplied(true);
		}
	};

	useEffect(() => {
		document.addEventListener('custom-dubai:open-cart', openCart);
		document.addEventListener('custom-dubai:close-cart', closeCart);

		const handleAddToCart = (event) => {
			const { product, quantity } = event.detail;
			setItems((prev) => {
				const existing = prev.find((item) => item.id === product.id);
				if (existing) {
					return prev.map((item) =>
						item.id === product.id
							? { ...item, quantity: item.quantity + quantity }
							: item
					);
				}
				return [...prev, { ...product, quantity }];
			});
			setIsOpen(true);
		};

		document.addEventListener('custom-dubai:add-to-cart', handleAddToCart);

		return () => {
			document.removeEventListener('custom-dubai:open-cart', openCart);
			document.removeEventListener('custom-dubai:close-cart', closeCart);
			document.removeEventListener('custom-dubai:add-to-cart', handleAddToCart);
		};
	}, [openCart, closeCart]);

	useEffect(() => {
		const newSubtotal = items.reduce((sum, item) => sum + item.price * item.quantity, 0);
		const newVat = newSubtotal * 0.05;
		setSubtotal(newSubtotal);
		setVat(newVat);
		setTotal(newSubtotal + newVat);
	}, [items]);

	return (
		<>
			<div
				className={`custom-dubai-side-cart__backdrop ${isOpen ? 'is-open' : ''}`}
				onClick={closeCart}
				aria-hidden="true"
			/>
			<aside
				className={`custom-dubai-side-cart ${isOpen ? 'is-open' : ''}`}
				aria-label="Shopping cart"
				aria-hidden={!isOpen}
			>
				<div className="custom-dubai-side-cart__header">
					<h2 className="custom-dubai-side-cart__title">Your Cart</h2>
					<button
						type="button"
						className="custom-dubai-side-cart__close"
						onClick={closeCart}
						aria-label="Close cart"
					>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18" />
							<line x1="6" y1="6" x2="18" y2="18" />
						</svg>
					</button>
				</div>

				<div className="custom-dubai-side-cart__items">
					{items.length === 0 ? (
						<div className="custom-dubai-side-cart__empty">
							<svg className="custom-dubai-side-cart__empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<circle cx="9" cy="21" r="1" />
								<circle cx="20" cy="21" r="1" />
								<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
							</svg>
							<p>Your cart is empty</p>
						</div>
					) : (
						items.map((item, index) => (
							<div key={item.id} className="custom-dubai-side-cart__item">
								<div className="custom-dubai-side-cart__item-image">
									<img src={item.image} alt={item.name} loading="lazy" />
								</div>
								<div className="custom-dubai-side-cart__item-details">
									<h3 className="custom-dubai-side-cart__item-name">{item.name}</h3>
									{item.size && (
										<p className="custom-dubai-side-cart__item-size">{item.size}</p>
									)}
									<p className="custom-dubai-side-cart__item-price">
										{formatPrice(item.price)}
									</p>
								</div>
								<div className="custom-dubai-side-cart__item-actions">
									<button
										type="button"
										className="custom-dubai-side-cart__item-remove"
										onClick={() => removeItem(index)}
										aria-label={`Remove ${item.name}`}
									>
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
											<polyline points="3 6 5 6 21 6" />
											<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
										</svg>
									</button>
									<div className="custom-dubai-side-cart__item-qty">
										<button
											type="button"
											className="custom-dubai-side-cart__qty-btn"
											onClick={() => updateQuantity(index, -1)}
											aria-label="Decrease quantity"
										>
											−
										</button>
										<span className="custom-dubai-side-cart__qty-value">{item.quantity}</span>
										<button
											type="button"
											className="custom-dubai-side-cart__qty-btn"
											onClick={() => updateQuantity(index, 1)}
											aria-label="Increase quantity"
										>
											+
										</button>
									</div>
								</div>
							</div>
						))
					)}
				</div>

				{items.length > 0 && (
					<div className="custom-dubai-side-cart__summary">
						<div className="custom-dubai-side-cart__notice">
							<svg className="custom-dubai-side-cart__notice-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<circle cx="12" cy="12" r="10" />
								<line x1="12" y1="16" x2="12" y2="12" />
								<line x1="12" y1="8" x2="12.01" y2="8" />
							</svg>
							<p>
								After checkout, a member of our team will confirm stock availability
								with you, and you'll receive a confirmation email.
							</p>
						</div>

						<div className="custom-dubai-side-cart__promo">
							<svg className="custom-dubai-side-cart__promo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
								<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" />
								<line x1="7" y1="7" x2="7.01" y2="7" />
							</svg>
							<input
								type="text"
								className="custom-dubai-side-cart__promo-input"
								placeholder="Promo code"
								value={promoCode}
								onChange={(e) => setPromoCode(e.target.value)}
								aria-label="Promo code"
							/>
							<button
								type="button"
								className="custom-dubai-side-cart__promo-btn"
								onClick={applyPromo}
							>
								{promoApplied ? 'Applied' : 'Apply'}
							</button>
						</div>

						<div className="custom-dubai-side-cart__totals">
							<div className="custom-dubai-side-cart__total-row">
								<span>Subtotal</span>
								<span>{formatPrice(subtotal)}</span>
							</div>
							<div className="custom-dubai-side-cart__total-row">
								<span>VAT (5%)</span>
								<span>{formatPrice(vat)}</span>
							</div>
							<div className="custom-dubai-side-cart__total-row custom-dubai-side-cart__total-row--grand">
								<span>Total</span>
								<span>{formatPrice(total)}</span>
							</div>
						</div>

						<div className="custom-dubai-side-cart__installments">
							<div className="custom-dubai-side-cart__installments-text">
								<p>
									As low as <strong>{formatPrice(total / 4)}</strong>/month or 4
									interest-free payments.
								</p>
								<a href="#" className="custom-dubai-side-cart__learn-more">Learn more</a>
							</div>
							<div className="custom-dubai-side-cart__tabby-badge">
								<span>tabby</span>
							</div>
						</div>

						<button type="button" className="custom-dubai-side-cart__checkout-btn">
							Proceed to Checkout
						</button>
					</div>
				)}
			</aside>
		</>
	);
}

function SideCartMount() {
	const container = document.getElementById('custom-dubai-side-cart');
	if (!container) return;

	const root = createRoot(container);
	root.render(<SideCart />);
}

SideCartMount();

export default SideCart;
