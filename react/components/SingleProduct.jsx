import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { MessageCircle, Wrench, Truck, ShoppingCart } from 'lucide-react';

function SingleProduct({ product }) {
	const [activeImage, setActiveImage] = useState(product.image?.url || '');
	const [quantity, setQuantity] = useState(4);
	const [added, setAdded] = useState(false);
	const [activeProductTab, setActiveProductTab] = useState('specifications');
	const gallery = [product.image, ...(product.gallery || [])].filter((image, index, all) => image?.url && all.findIndex((item) => item?.url === image.url) === index);
	const meta = product.meta || {};
	const price = Number(product.price || 0);
	const formatPrice = (value) => new Intl.NumberFormat('en-AE', {
		style: 'currency', currency: product.currency || 'AED', minimumFractionDigits: 0,
	}).format(value).replace('AED', 'AED ');

	const addToCart = () => {
		const cartProduct = {
			id: product.id,
			name: product.name,
			image: product.image?.url || '',
			price,
		size: meta.size_label || '',
		};
		document.dispatchEvent(new CustomEvent('custom-dubai:add-to-cart', { detail: { product: cartProduct, quantity } }));
		setAdded(true);
		window.setTimeout(() => setAdded(false), 1800);
	};

	return (
		<div className="dts-single-product">
			<nav className="dts-single-product__breadcrumbs" aria-label="Breadcrumb">
				<a href="/">Home</a><span>/</span><a href={product.shop_url}>Tyres</a><span>/</span><span>{product.name}</span>
			</nav>
			<div className="dts-single-product__layout">
				<section className="dts-single-product__gallery" aria-label="Product images">
					<div className="dts-single-product__hero-image">
						{product.on_sale && <span className="dts-single-product__sale-tag">Special price</span>}
						<img src={activeImage} alt={product.name} />
					</div>
					{gallery.length > 1 && <div className="dts-single-product__thumbnails">
						{gallery.map((image) => <button key={image.id || image.url} className={activeImage === image.url ? 'is-active' : ''} type="button" onClick={() => setActiveImage(image.url)} aria-label="View product image">
							<img src={image.url} alt={image.alt || product.name} />
						</button>)}
					</div>}
				</section>

				<section className="dts-single-product__details">
					<div className="dts-single-product__eyebrow">{product.brand_name || product.type_label || 'Tyre'}</div>
					<h1>{product.name}</h1>
					{meta.size_label && <div className="dts-single-product__current-size">{meta.size_label}</div>}
					<div className="dts-single-product__rating">
						<span aria-label="5 out of 5 stars">★★★★★</span><span>{product.rating?.count ? `${product.rating.count} reviews` : 'Quality checked'}</span>
					</div>

					{product.size_options?.length > 1 && <div className="dts-single-product__option-block">
						<div className="dts-single-product__option-heading"><strong>Tyre size</strong><span>{meta.size_label}</span></div>
						<div className="dts-single-product__size-options">
							{product.size_options.map((option) => <a key={option.id} href={option.permalink} className={`${option.current ? 'is-selected' : ''} ${!option.in_stock ? 'is-unavailable' : ''}`} aria-current={option.current ? 'page' : undefined}>
								<span>{option.label}</span><small>{formatPrice(option.price)}</small>
							</a>)}
						</div>
					</div>}

					<div className="dts-single-product__price-panel">
						<div><strong>{formatPrice(price)}</strong><span> / tyre</span></div>
						{product.regular_price > price && <del>{formatPrice(product.regular_price)}</del>}
						<small>All prices include VAT</small>
					</div>

					<div className="dts-single-product__purchase">
						<div className="dts-single-product__quantity-row"><strong>Quantity:</strong><div className="dts-single-product__quantity" aria-label="Quantity">
							<button type="button" onClick={() => setQuantity((current) => Math.max(1, current - 1))} aria-label="Decrease quantity">−</button>
							<span>{quantity}</span>
							<button type="button" onClick={() => setQuantity((current) => Math.min(20, current + 1))} aria-label="Increase quantity">+</button>
						</div></div>
						<button type="button" className="dts-single-product__add" disabled={!product.purchasable} onClick={addToCart}>
							{!added && product.purchasable && <ShoppingCart aria-hidden="true" />}{added ? 'Added to cart' : product.purchasable ? 'Add to Cart' : 'Currently unavailable'}
						</button>
					</div>
					{product.whatsapp && <a className="dts-single-product__whatsapp" href={`https://wa.me/${product.whatsapp}?text=${encodeURIComponent(`Hi, I have a question about ${product.name}`)}`} target="_blank" rel="noreferrer"><MessageCircle aria-hidden="true" /><strong>Chat on WhatsApp about this tyre</strong><span>or call us and we will help</span></a>}
					<div className="dts-single-product__service-cards">
						<div><Wrench aria-hidden="true" /><p><strong>Fitment Included</strong><small>Free at partner locations</small></p></div>
						<div><Truck aria-hidden="true" /><p><strong>Mobile Fitting (optional)</strong><small>Tyres at your location</small></p></div>
					</div>
				</section>
			</div>

			<section className="dts-single-product__product-info" aria-label="Product information">
				<div className="dts-single-product__tabs" role="tablist" aria-label="Product information tabs">
					<button id="product-tab-specifications" className={activeProductTab === 'specifications' ? 'is-active' : ''} type="button" role="tab" aria-selected={activeProductTab === 'specifications'} aria-controls="product-panel-specifications" onClick={() => setActiveProductTab('specifications')}>Product details</button>
					{product.description && <button id="product-tab-description" className={activeProductTab === 'description' ? 'is-active' : ''} type="button" role="tab" aria-selected={activeProductTab === 'description'} aria-controls="product-panel-description" onClick={() => setActiveProductTab('description')}>Description</button>}
				</div>
				<div className="dts-single-product__tab-panel" id="product-panel-specifications" role="tabpanel" aria-labelledby="product-tab-specifications" hidden={activeProductTab !== 'specifications'}>
					<h2>Product details</h2>
					<dl>
						{[
							['Tyre size', meta.size_label], ['Brand', product.brand_name], ['Model', meta.model || product.name],
							['Load index', meta.load_index], ['Speed rating', meta.speed_rating], ['Sidewall', meta.sidewall],
							['Country of origin', meta.origin, meta.origin_flag], ['Manufacturing year', meta.year], ['Warranty', meta.warranty],
							['Run flat', meta.run_flat ? 'Yes' : 'No'],
						].filter(([, value]) => value).map(([label, value, flag]) => <div key={label}>
							<dt>{label}</dt>
							<dd>
								{flag ? <span className="dts-single-product__origin-flag" role="img" aria-label={value}>{flag}</span> : null}
								{flag ? ' ' : null}
								{value}
							</dd>
						</div>)}
					</dl>
				</div>
				{product.description && <div className="dts-single-product__tab-panel dts-single-product__description" id="product-panel-description" role="tabpanel" aria-labelledby="product-tab-description" hidden={activeProductTab !== 'description'} dangerouslySetInnerHTML={{ __html: product.description }} />}
			</section>
		</div>
	);
}

const mount = document.getElementById('dts-single-product-root');
if (mount && window.DTSSingleProduct) createRoot(mount).render(<SingleProduct product={window.DTSSingleProduct} />);

export default SingleProduct;

