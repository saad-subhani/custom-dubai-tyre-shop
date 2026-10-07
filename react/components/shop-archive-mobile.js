(function () {
	var media = window.matchMedia('(max-width: 680px)');
	var mountedRoot = null;
	var resizeObserver = null;
	var bodyOverflow = '';

	function icon(name) {
		var paths = {
			filters: '<path d="M4 6h16M7 12h10m-7 6h4"/><circle cx="8" cy="6" r="2"/><circle cx="15" cy="12" r="2"/>',
			size: '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/>',
			vehicle: '<path d="m5 11 1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11M3 11h18v8H3z"/><circle cx="7" cy="16" r="1"/><circle cx="17" cy="16" r="1"/>',
			sort: '<path d="M8 4v16m0 0-4-4m4 4 4-4m4-12v16m0-16-4 4m4-4 4 4"/>',
			chevron: '<path d="m7 10 5 5 5-5"/>'
		};
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths[name] + '</svg>';
	}

	function mount() {
		var root = document.querySelector('.dts-shop-archive');
		if (!root || mountedRoot === root) return;
		var toolbar = root.querySelector('.dts-catalog-toolbar');
		var main = root.querySelector('.collections-main');
		var header = root.querySelector('.collections-header');
		var search = toolbar && toolbar.querySelector('.dts-catalog-toolbar__search');
		var sort = toolbar && toolbar.querySelector('.dts-catalog-toolbar__sort');
		var originalFilter = root.querySelector('.mobile-filter-btn');
		if (!toolbar || !main || !header || !search || !sort) return;
		mountedRoot = root;

		var sticky = document.createElement('div');
		sticky.className = 'dts-mobile-filter-sticky';
		sticky.innerHTML = '<div class="dts-mobile-filter-controls">' +
			'<button type="button" class="dts-mobile-filter-action dts-mobile-filter-action--filters" aria-label="Open filters">' + icon('filters') + '<span>Filters</span></button>' +
			'<button type="button" class="dts-mobile-filter-action" data-dts-modal="size">' + icon('size') + '<span>Size</span></button>' +
			'<button type="button" class="dts-mobile-filter-action" data-dts-modal="vehicle">' + icon('vehicle') + '<span>Vehicle</span></button>' +
			'<div class="dts-mobile-sort"></div>' +
			'</div><div class="dts-mobile-filter-search"></div>';
		main.insertBefore(sticky, header.nextSibling);
		sticky.querySelector('.dts-mobile-sort').appendChild(sort);
		sticky.querySelector('.dts-mobile-filter-search').appendChild(search);
		var countNode = root.querySelector('.collections-result-count');
		var toolbarCount = toolbar.querySelector('.dts-catalog-toolbar__count');
		if (countNode && toolbarCount) {
			var countObserver = new MutationObserver(function () { countNode.textContent = toolbarCount.textContent; });
			countObserver.observe(toolbarCount, { childList: true, characterData: true, subtree: true });
		}

		sticky.querySelector('.dts-mobile-filter-action--filters').addEventListener('click', function () {
			if (originalFilter) originalFilter.click();
		});
		sticky.querySelectorAll('[data-dts-modal]').forEach(function (button) {
			button.addEventListener('click', function () {
				var eventName = button.dataset.dtsModal === 'vehicle' ? 'custom-dubai:open-vehicle-modal' : 'custom-dubai:open-tyre-modal';
				document.dispatchEvent(new CustomEvent(eventName));
			});
		});

		function syncLayout() {
			if (media.matches) {
				if (sticky.parentNode !== main) main.insertBefore(sticky, header.nextSibling);
				if (sort.parentNode !== sticky.querySelector('.dts-mobile-sort')) sticky.querySelector('.dts-mobile-sort').appendChild(sort);
				if (search.parentNode !== sticky.querySelector('.dts-mobile-filter-search')) sticky.querySelector('.dts-mobile-filter-search').appendChild(search);
			} else {
				var catalogTools = toolbar.querySelector('.dts-catalog-toolbar__catalog-tools');
				if (catalogTools) {
					if (search.parentNode !== catalogTools) catalogTools.insertBefore(search, catalogTools.firstChild);
					if (sort.parentNode !== catalogTools) catalogTools.insertBefore(sort, toolbarCount || null);
				}
			}
		}
		syncLayout();
		media.addEventListener ? media.addEventListener('change', syncLayout) : media.addListener(syncLayout);
		window.addEventListener('resize', syncLayout, { passive: true });

		var sidebar = root.querySelector('.collections-sidebar');
		if (sidebar) {
			var close = sidebar.querySelector('.drawer-close-btn');
			var handle = document.createElement('span');
			handle.className = 'dts-filter-sheet-handle';
			handle.setAttribute('aria-hidden', 'true');
			sidebar.insertBefore(handle, sidebar.firstChild);
			if (close) close.innerHTML = '<span aria-hidden="true">×</span>';
		}
		var backdrop = root.querySelector('.mobile-filter-backdrop');
		var previousOpen = false;
		function lockScroll() {
			var open = root.classList.contains('filters-open');
			if (open === previousOpen) return;
			previousOpen = open;
			if (open) {
				bodyOverflow = document.body.style.overflow;
				document.body.style.overflow = 'hidden';
			} else {
				document.body.style.overflow = bodyOverflow;
			}
		}
		if (backdrop) {
			resizeObserver = new MutationObserver(lockScroll);
			resizeObserver.observe(root, { attributes: true, attributeFilter: ['class'] });
		}
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && root.classList.contains('filters-open') && backdrop) backdrop.click();
		});
	}

	function start() {
		mount();
		var observer = new MutationObserver(mount);
		observer.observe(document.body, { childList: true, subtree: true });
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
	else start();
})();
