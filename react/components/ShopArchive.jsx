import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Search, CarFront, Info, CalendarDays, Globe2, Shield, Crown, BadgeCheck, WalletCards, ShoppingCart, CircleCheck, Wrench, ArrowUpDown, ChevronDown } from 'lucide-react';

const config = window.DTSShopArchive || {};

function getInitialFilters() {
	const params = new URLSearchParams(window.location.search);
	const serverFilters = config.filters || {};
	const values = (queryName, key) => {
		const fromUrl = params.getAll(queryName);
		return fromUrl.length ? fromUrl : (serverFilters[key] || []);
	};
	const min = params.get('price_min');
	const max = params.get('price_max');
	return {
		brand: values('filter_brand[]', 'brand'),
		year: values('filter_year[]', 'year'),
		run_flat: values('filter_run_flat[]', 'run_flat'),
		promo: values('filter_promo[]', 'promo'),
		origin: values('filter_origin[]', 'origin'),
		capacity: values('filter_capacity[]', 'capacity'),
		reserve: values('filter_reserve[]', 'reserve'),
		cca: values('filter_cca[]', 'cca'),
		length: values('filter_length[]', 'length'),
		width: values('filter_width[]', 'width'),
		height: values('filter_height[]', 'height'),
		rim: values('filter_rim[]', 'rim'),
		tyreType: values('filter_tyre_type[]', 'tyreType'),
		vehicleType: values('filter_vehicle_type[]', 'vehicleType'),
		tier: values('filter_tier[]', 'tier'),
		priceMin: min === null ? serverFilters.price_min : Number(min),
		priceMax: max === null ? serverFilters.price_max : Number(max),
		priceActive: min !== null || max !== null || serverFilters.price_min !== null || serverFilters.price_max !== null,
		onSale: params.get('on_sale') === '1' || Boolean(serverFilters.on_sale),
		orderby: params.get('orderby') || serverFilters.orderby || 'menu_order',
		search: params.get('search') || '',
	};
}

function currency(value) {
	return new Intl.NumberFormat('en-AE', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function ArchiveProductCard({ item }) {
	const [quantity, setQuantity] = useState(4);
	const [added, setAdded] = useState(false);
	const image = typeof item.image === 'string' ? item.image : item.image?.url;
	const meta = item.meta || {};
	const brand = item.brand || {};
	const size = meta.size_label || ([meta.width, meta.height, meta.rim ? `R${String(meta.rim).replace(/^R/i, '')}` : ''].filter(Boolean).join('/'));
	const displayPrice = Number(item.price_tax_incl || item.price || 0);
	const name = item.name || meta.model || '';
	const warrantyMatch = String(meta.warranty || '').match(/(\d+)/);
	const warranty = warrantyMatch ? `${warrantyMatch[1]}yr` : meta.warranty;
	const tier = String(meta.tyre_tier || '').trim();

	const addToCart = () => {
		if (!item.in_stock) return;
		setAdded(true);
		window.setTimeout(() => setAdded(false), 1800);
		document.dispatchEvent(new CustomEvent('custom-dubai:add-to-cart', {
			detail: {
				product: {
					id: item.id,
					name,
					image,
					size,
					price: displayPrice,
					currency: item.currency || 'AED',
					permalink: item.permalink,
				},
				quantity,
			},
		}));
	};

	return <article className="dts-reference-card">
		<div className="dts-reference-card__guarantee">{brand.logo && <a href={brand.url || '#'} aria-label={brand.name}><img src={brand.logo} alt={brand.name || ''} loading="lazy" /></a>}<span><BadgeCheck size={16} aria-hidden="true" />Best Price Guarantee</span></div>
		<div className="dts-reference-card__media"><a href={item.permalink || '#'} aria-label={name}><img src={image || ''} alt={item.name || ''} loading="lazy" /></a>
			{brand.logo && <a className="dts-reference-card__brand" href={brand.url || '#'} aria-label={brand.name}><img src={brand.logo} alt={brand.name || ''} loading="lazy" /></a>}
		</div>
		<div className="dts-reference-card__body">
			<h3 className="dts-reference-card__title"><a href={item.permalink || '#'}>{name}</a><span>{size}</span></h3>
			<div className="dts-reference-card__meta"><span aria-label="Production year"><ArchiveIcon name="calendar" size={16} />{meta.year || '—'}</span><span aria-label="Country of origin">{meta.origin_flag ? <span className="dts-reference-card__flag" role="img" aria-label={meta.origin}>{meta.origin_flag}</span> : <ArchiveIcon name="globe" size={16} />}{meta.origin || '—'}</span>{warranty && <span aria-label="Warranty"><ArchiveIcon name="shield" size={16} />{warranty}</span>}</div>
			<div className="dts-reference-card__tags">{tier && <span className="dts-reference-card__tag dts-reference-card__tag--tier">{tier}</span>}{item.badges?.some((badge) => badge.key === 'run_flat') && <span className="dts-reference-card__tag">Run Flat</span>}{meta.vehicle_type && <span className="dts-reference-card__tag">{meta.vehicle_type}</span>}{meta.buy_3_get_1 && <span className="dts-reference-card__tag">Buy 3 Get 1</span>}</div>
			<div className="dts-reference-card__price"><strong><span className="dts-reference-card__currency">د.إ</span>{currency(displayPrice)}</strong><span>/tyre</span></div>
			<p className="dts-reference-card__set">Set of 4: <strong><span className="dts-reference-card__currency">د.إ</span>{currency(displayPrice * 4)}</strong></p>
			<div className="dts-reference-card__actions"><div className="dts-reference-card__quantity"><button type="button" aria-label="Decrease quantity" onClick={() => setQuantity((value) => Math.max(1, value - 1))}>−</button><span>{quantity}</span><button type="button" aria-label="Increase quantity" onClick={() => setQuantity((value) => value + 1)}>+</button></div><button type="button" className="dts-reference-card__add" onClick={addToCart} disabled={!item.in_stock}>{added ? 'Added' : <><ArchiveIcon name="cart" size={16} />Add to Cart</>}</button></div>
			<div className="dts-reference-card__footer"><span className="dts-reference-card__stock"><ArchiveIcon name="check" size={16} />In Stock</span><span><ArchiveIcon name="wrench" size={16} />Fitment Included <ArchiveIcon name="info" size={14} /></span></div>
		</div>
	</article>;
}

function FilterGroup({ title, children, defaultOpen = false }) {
	const [open, setOpen] = useState(defaultOpen);
	return <section className={`filter-group${open ? ' is-open' : ''}`}>
		<button type="button" className="filter-group-toggle" aria-expanded={open} onClick={() => setOpen(!open)}><span className="filter-group-label">{title}</span><span className="filter-group-chevron"><svg viewBox="0 0 24 24" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9" /></svg></span></button>
		<div className="filter-group-body">{children}</div>
	</section>;
}

function ArchiveIcon({ name, size = 18 }) {
	const icons = { search: Search, car: CarFront, info: Info, calendar: CalendarDays, globe: Globe2, shield: Shield, crown: Crown, quality: BadgeCheck, wallet: WalletCards, cart: ShoppingCart, check: CircleCheck, wrench: Wrench, sort: ArrowUpDown, chevron: ChevronDown };
	const Icon = icons[name] || Info;
	return <Icon size={size} strokeWidth={1.8} aria-hidden="true" />;
}

function SizeFacet({ label, options, value, onChange }) {
	return <label className="dts-size-facet"><span>{label}</span><select value={value[0] || ''} onChange={(event) => onChange(event.target.value ? [event.target.value] : [])}>
		<option value="">All</option>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
	</select></label>;
}

function CheckOptions({ options = [], selected = [], onChange, name, searchable = false }) {
	const [search, setSearch] = useState('');
	const filtered = options.filter((option) => String(option.label ?? option.name ?? option.value).toLowerCase().includes(search.toLowerCase()));
	return <>
		{searchable && <div className="filter-brand-search"><input className="filter-brand-search-input" type="search" placeholder="Search brand..." value={search} onChange={(event) => setSearch(event.target.value)} aria-label="Search brands" /></div>}
		<ul className={`filter-checkbox-list${searchable ? ' filter-brand-checkbox-list' : ''}`}>
			{filtered.map((option) => {
				const value = String(option.slug ?? option.value);
				const checked = selected.includes(value);
				const id = `${name}-${value.replace(/[^a-z0-9_-]/gi, '-')}`;
				return <li className="filter-checkbox-item" key={value}><label htmlFor={id}><input id={id} type="checkbox" name={`${name}[]`} checked={checked} onChange={() => onChange(checked ? selected.filter((item) => item !== value) : [...selected, value])} />{option.label ?? option.name ?? value}{option.count > 0 ? <span className="filter-item-count"> ({option.count})</span> : null}</label></li>;
			})}
		</ul>
	</>;
}

function ShopArchive() {
	const [filters, setFilters] = useState(getInitialFilters);
	const [facets, setFacets] = useState({});
	const [products, setProducts] = useState([]);
	const [count, setCount] = useState(0);
	const [page, setPage] = useState(1);
	const [totalPages, setTotalPages] = useState(1);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');
	const [mobileOpen, setMobileOpen] = useState(false);
	const requestId = useRef(0);
	const isBattery = config.family === 'battery';
	const filterGroups = isBattery
		? [['origin', 'Country of Manufacture'], ['capacity', 'Capacity'], ['reserve', 'Reserve Capacity'], ['cca', 'CCA'], ['length', 'Length'], ['width', 'Width'], ['height', 'Height']]
		: [['year', 'Production Year']];
	const trailingFilterGroups = isBattery ? [] : [['promo', 'Buy 3 Get 1 Free'], ['run_flat', 'Tyre Run Flat']];

	const requestProducts = useCallback(async (currentFilters, currentPage = 1) => {
		const request = ++requestId.current;
		setLoading(true);
		setError('');
		const data = new FormData();
		data.set('action', isBattery ? 'dts_battery_filter_grid' : 'dts_filter_grid');
		data.set('paged', String(currentPage));
		data.set('per_page', '16');
		data.set('orderby', currentFilters.orderby || 'menu_order');
		if (config.productCategory) data.set('product_cat', config.productCategory);
		if (config.brandSlug) data.set('brand_page_slug', config.brandSlug);
		if (isBattery) {
			[['brand', 'filter_brand'], ['origin', 'filter_origin'], ['capacity', 'filter_capacity'], ['reserve', 'filter_reserve'], ['cca', 'filter_cca'], ['length', 'filter_length'], ['width', 'filter_width'], ['height', 'filter_height']].forEach(([key, field]) => currentFilters[key].forEach((value) => data.append(`${field}[]`, value)));
			data.set('on_sale', currentFilters.onSale ? '1' : '');
		} else {
			[['brand', 'filter_brand'], ['year', 'filter_year'], ['run_flat', 'filter_run_flat'], ['promo', 'filter_promo'], ['width', 'filter_width'], ['height', 'filter_height'], ['rim', 'filter_rim'], ['tyreType', 'filter_tyre_type'], ['vehicleType', 'filter_vehicle_type'], ['tier', 'filter_tier']].forEach(([key, field]) => currentFilters[key].forEach((value) => data.append(`${field}[]`, value)));
		}
		if (currentFilters.search) data.set('search', currentFilters.search);
		if (currentFilters.priceActive) {
			data.set('price_filter_active', '1');
			if (currentFilters.priceMin !== null) data.set('price_min', String(currentFilters.priceMin));
			if (currentFilters.priceMax !== null) data.set('price_max', String(currentFilters.priceMax));
		}

		try {
			const response = await fetch(config.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' });
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result?.data?.message || 'Could not load products. Please try again.');
			if (request !== requestId.current) return;
			setProducts(result.data.products || []);
			setFacets(result.data.facets || {});
			setCount(Number(result.data.count || 0));
			setPage(Number(result.data.paged || currentPage));
			setTotalPages(Number(result.data.total_pages || 1));
		} catch (fetchError) {
			if (request === requestId.current) setError(fetchError.message || 'Could not load products. Please try again.');
		} finally {
			if (request === requestId.current) setLoading(false);
		}
	}, [isBattery]);

	useEffect(() => {
		const timer = window.setTimeout(() => requestProducts(filters, page), 220);
		return () => window.clearTimeout(timer);
	}, [filters, page, requestProducts]);

	useEffect(() => {
		setPage(1);
	}, [filters]);

	useEffect(() => {
		const url = new URL(window.location.href);
		['filter_brand[]', 'filter_year[]', 'filter_run_flat[]', 'filter_promo[]', 'filter_origin[]', 'filter_capacity[]', 'filter_reserve[]', 'filter_cca[]', 'filter_length[]', 'filter_width[]', 'filter_height[]', 'filter_rim[]', 'filter_tyre_type[]', 'filter_vehicle_type[]', 'filter_tier[]', 'price_min', 'price_max', 'price_filter_active', 'on_sale', 'orderby', 'search'].forEach((key) => url.searchParams.delete(key));
		[['brand', 'filter_brand[]'], ['year', 'filter_year[]'], ['run_flat', 'filter_run_flat[]'], ['promo', 'filter_promo[]'], ['origin', 'filter_origin[]'], ['capacity', 'filter_capacity[]'], ['reserve', 'filter_reserve[]'], ['cca', 'filter_cca[]'], ['length', 'filter_length[]'], ['width', 'filter_width[]'], ['height', 'filter_height[]'], ['rim', 'filter_rim[]'], ['tyreType', 'filter_tyre_type[]'], ['vehicleType', 'filter_vehicle_type[]'], ['tier', 'filter_tier[]']].forEach(([key, param]) => filters[key].forEach((value) => url.searchParams.append(param, value)));
		if (filters.search) url.searchParams.set('search', filters.search);
		if (filters.priceActive) {
			if (filters.priceMin !== null) url.searchParams.set('price_min', String(filters.priceMin));
			if (filters.priceMax !== null) url.searchParams.set('price_max', String(filters.priceMax));
			url.searchParams.set('price_filter_active', '1');
		}
		if (filters.onSale) url.searchParams.set('on_sale', '1');
		if (filters.orderby !== 'menu_order') url.searchParams.set('orderby', filters.orderby);
		window.history.replaceState({}, '', url);
	}, [filters]);

	const setArrayFilter = (key, value) => setFilters((previous) => ({ ...previous, [key]: value }));
	const price = facets.price || {};
	const minBound = Number(isBattery ? price.absolute_min ?? price.min ?? 0 : price.min ?? 0);
	const maxBound = Number(isBattery ? price.absolute_max ?? price.max ?? 5000 : price.max ?? 5000);
	const minValue = filters.priceMin ?? minBound;
	const maxValue = filters.priceMax ?? maxBound;
	const brands = facets.brands || [];
	const activeCount = useMemo(() => ['brand', 'year', 'run_flat', 'promo', 'origin', 'capacity', 'reserve', 'cca', 'length', 'width', 'height', 'rim', 'tyreType', 'vehicleType', 'tier'].reduce((sum, key) => sum + filters[key].length, 0) + (filters.priceActive ? 1 : 0) + (filters.onSale ? 1 : 0) + (filters.search ? 1 : 0), [filters]);
	const clearFilters = () => setFilters((previous) => ({ ...previous, brand: [], year: [], run_flat: [], promo: [], origin: [], capacity: [], reserve: [], cca: [], length: [], width: [], height: [], rim: [], tyreType: [], vehicleType: [], tier: [], priceMin: null, priceMax: null, priceActive: false, onSale: false, search: '' }));
	const optionsFor = (key) => facets[key] || [];

	return <div className={`collections-page dts-shop-archive${mobileOpen ? ' filters-open' : ''}`}>
		<button type="button" className="mobile-filter-backdrop" aria-label="Close filters" onClick={() => setMobileOpen(false)} />
		<div className="dts-catalog-toolbar">
			<div className="dts-catalog-toolbar__search-tools">
				<button type="button" onClick={() => document.dispatchEvent(new CustomEvent('custom-dubai:open-tyre-modal'))}><ArchiveIcon name="search" />Search by Size</button>
				<button type="button" onClick={() => document.dispatchEvent(new CustomEvent('custom-dubai:open-vehicle-modal'))}><ArchiveIcon name="car" />Search by Vehicle</button>
				<button type="button" className="dts-catalog-toolbar__help" onClick={() => document.dispatchEvent(new CustomEvent('custom-dubai:open-tyre-modal'))}><ArchiveIcon name="info" />Learn to Read Your Tyre Size</button>
			</div>
			<div className="dts-catalog-toolbar__catalog-tools">
				<label className="dts-catalog-toolbar__search"><ArchiveIcon name="search" /><input type="search" placeholder="Search brand, model or size..." value={filters.search} onChange={(event) => setFilters((previous) => ({ ...previous, search: event.target.value }))} /></label>
				<label className="dts-catalog-toolbar__sort"><ArchiveIcon name="sort" /><span className="screen-reader-text">Sort products</span><select value={filters.orderby} onChange={(event) => setFilters((previous) => ({ ...previous, orderby: event.target.value }))}><option value="menu_order">Sort</option><option value="price">Price: low to high</option><option value="price-desc">Price: high to low</option><option value="date">Newest</option><option value="popularity">Popularity</option></select><ArchiveIcon name="chevron" /></label>
				<span className="dts-catalog-toolbar__count">{loading ? 'Loading tyres...' : `${count.toLocaleString()} tyres found`}</span>
			</div>
		</div>
		<div className="collections-container">
			<aside className="collections-sidebar" id="collections-sidebar" aria-label="Product filters">
				<div className="drawer-header"><h2 className="drawer-header-title">Filters</h2><button type="button" className="drawer-close-btn" onClick={() => setMobileOpen(false)} aria-label="Close filters">×</button></div>
				<div className="filter-panel">
					<FilterGroup title="Tyre Size" defaultOpen><div className="dts-size-facets"><SizeFacet label="Width" options={optionsFor('width')} value={filters.width} onChange={(values) => setArrayFilter('width', values)} /><SizeFacet label="Profile" options={optionsFor('height')} value={filters.height} onChange={(values) => setArrayFilter('height', values)} /><SizeFacet label="Rim Size" options={optionsFor('rim')} value={filters.rim} onChange={(values) => setArrayFilter('rim', values)} /></div></FilterGroup>
					{!isBattery && <FilterGroup title="Price Range" defaultOpen><div className="filter-price-range"><div className="filter-range-wrap"><div className="filter-range-track" /><div className="filter-range-fill" style={{ left: `${maxBound > minBound ? ((minValue - minBound) / (maxBound - minBound)) * 100 : 0}%`, right: `${maxBound > minBound ? 100 - ((maxValue - minBound) / (maxBound - minBound)) * 100 : 0}%` }} /><input className="filter-range-input filter-range-min" type="range" min={minBound} max={maxBound} step="1" value={Math.min(minValue, maxValue)} onChange={(event) => setFilters((previous) => ({ ...previous, priceMin: Math.min(Number(event.target.value), previous.priceMax ?? maxBound), priceActive: true }))} aria-label="Minimum price" /><input className="filter-range-input filter-range-max" type="range" min={minBound} max={maxBound} step="1" value={Math.max(maxValue, minValue)} onChange={(event) => setFilters((previous) => ({ ...previous, priceMax: Math.max(Number(event.target.value), previous.priceMin ?? minBound), priceActive: true }))} aria-label="Maximum price" /></div><div className="dts-price-values"><label><span>د.إ</span><input className="filter-price-value-input" type="number" min={minBound} max={maxValue} value={minValue} onChange={(event) => setFilters((previous) => ({ ...previous, priceMin: Number(event.target.value), priceActive: true }))} /></label><span>–</span><label><span>د.إ</span><input className="filter-price-value-input" type="number" min={minValue} max={maxBound} value={maxValue} onChange={(event) => setFilters((previous) => ({ ...previous, priceMax: Number(event.target.value), priceActive: true }))} /></label></div></div></FilterGroup>}
					{!isBattery && <FilterGroup title="Tier" defaultOpen><div className="dts-tier-options">{[['premium', 'crown', 'Premium'], ['quality', 'quality', 'Quality'], ['budget', 'wallet', 'Budget']].map(([value, icon, label]) => <button type="button" key={value} className={filters.tier.includes(value) ? 'is-active' : ''} onClick={() => setArrayFilter('tier', filters.tier.includes(value) ? filters.tier.filter((tier) => tier !== value) : [...filters.tier, value])}><ArchiveIcon name={icon} />{label}</button>)}</div></FilterGroup>}
					{!isBattery && <FilterGroup title="Tyre Type"><CheckOptions options={optionsFor('tyreType')} selected={filters.tyreType} onChange={(values) => setArrayFilter('tyreType', values)} name="filter-tyre-type" /></FilterGroup>}
					{!isBattery && <FilterGroup title="Vehicle Type"><CheckOptions options={optionsFor('vehicleType')} selected={filters.vehicleType} onChange={(values) => setArrayFilter('vehicleType', values)} name="filter-vehicle-type" /></FilterGroup>}
					{brands.length > 0 && <FilterGroup title="Brand"><CheckOptions options={brands} selected={filters.brand} onChange={(values) => setArrayFilter('brand', values)} name="filter-brand" searchable /></FilterGroup>}
					{filterGroups.map(([key, title]) => optionsFor(key).length > 0 && <FilterGroup title={title} key={key}><CheckOptions options={optionsFor(key)} selected={filters[key]} onChange={(values) => setArrayFilter(key, values)} name={`filter-${key}`} /></FilterGroup>)}
					{trailingFilterGroups.map(([key, title]) => optionsFor(key).length > 0 && <FilterGroup title={title} key={key}><CheckOptions options={optionsFor(key)} selected={filters[key]} onChange={(values) => setArrayFilter(key, values)} name={`filter-${key}`} /></FilterGroup>)}
					{isBattery && <FilterGroup title="Price (AED)"><div className="filter-price-inputs"><label className="filter-price-input-wrap"><span className="filter-price-input-label">Min</span><input className="filter-price-value-input" type="number" min={minBound} max={maxBound} value={minValue} onChange={(event) => setFilters((previous) => ({ ...previous, priceMin: Number(event.target.value), priceActive: true }))} /></label><label className="filter-price-input-wrap"><span className="filter-price-input-label">Max</span><input className="filter-price-value-input" type="number" min={minBound} max={maxBound} value={maxValue} onChange={(event) => setFilters((previous) => ({ ...previous, priceMax: Number(event.target.value), priceActive: true }))} /></label></div></FilterGroup>}
					{isBattery && <FilterGroup title="On Sale"><label className="filter-checkbox-item"><input type="checkbox" checked={filters.onSale} onChange={(event) => setFilters((previous) => ({ ...previous, onSale: event.target.checked }))} /> On sale</label></FilterGroup>}
				</div>
			</aside>
			<main className="collections-main" id="main">
				<div className="collections-header"><div className="collections-header-left"><h1 className="collections-page-title">{config.title || 'Shop'}</h1><p className="collections-result-count" aria-live="polite">{loading ? 'Updating products…' : `${count.toLocaleString()} results`}</p></div><div className="collections-header-right"><button type="button" className="mobile-filter-btn" onClick={() => setMobileOpen(true)} aria-expanded={mobileOpen}><ArchiveIcon name="search" /> Filters{activeCount ? ` (${activeCount})` : ''}</button></div></div>
				<div className="active-filters-bar" aria-live="polite">{['brand', 'year', 'run_flat', 'promo', 'origin', 'capacity', 'reserve', 'cca', 'length', 'width', 'height'].flatMap((key) => filters[key].map((value) => <button type="button" className="active-filter-chip" key={`${key}-${value}`} onClick={() => setArrayFilter(key, filters[key].filter((item) => item !== value))}><span className="active-filter-chip-label">{key.replace('_', ' ')}: {value}</span><span className="active-filter-chip-remove">×</span></button>))}{filters.priceActive && <button type="button" className="active-filter-chip" onClick={() => setFilters((previous) => ({ ...previous, priceMin: null, priceMax: null, priceActive: false }))}><span className="active-filter-chip-label">AED {minValue}–{maxValue}</span><span className="active-filter-chip-remove">×</span></button>}</div>
				<div className="collections-grid-wrap" aria-busy={loading}>
					{error ? <div className="collections-no-results"><h3>Products could not be loaded</h3><p>{error}</p><button type="button" className="button" onClick={() => requestProducts(filters, page)}>Try again</button></div> : products.length ? <ul className="products dts-shop-archive__grid">{products.map((product) => <li className="product" key={product.id}><ArchiveProductCard item={product} /></li>)}</ul> : !loading && <div className="collections-no-results"><h3>No products found</h3><p>Try changing your filters or clear them to see more products.</p><button type="button" className="button" onClick={clearFilters}>Clear filters</button></div>}
				</div>
				{totalPages > 1 && <nav className="collections-pagination" aria-label="Product pages"><button type="button" className="button" disabled={page <= 1 || loading} onClick={() => setPage((current) => Math.max(1, current - 1))}>Previous</button><span>Page {page} of {totalPages}</span><button type="button" className="button" disabled={page >= totalPages || loading} onClick={() => setPage((current) => Math.min(totalPages, current + 1))}>Next</button></nav>}
			</main>
		</div>
	</div>;
}

function mountShopArchive() {
	const node = document.getElementById('dts-shop-archive-root');
	if (node) createRoot(node).render(<ShopArchive />);
}

mountShopArchive();
