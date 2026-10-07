/**
 * Globe section.
 *
 * The copy is rendered by the shortcode in PHP so it stays indexable, and this
 * component owns the globe itself. It stays tiny on purpose: the section ships
 * nothing but React, then downloads the three.js engine chunk only once the
 * globe is about to scroll into view.
 */
import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

/** Start fetching the engine this far before the globe is actually on screen. */
const PRELOAD_MARGIN = '360px 0px';

/** Shared by every instance, so the engine chunk is fetched at most once. */
let enginePromise = null;

function loadEngine(src) {
	if (window.CDTSGlobeEngine) return Promise.resolve(window.CDTSGlobeEngine);
	if (enginePromise) return enginePromise;

	enginePromise = new Promise((resolve, reject) => {
		const script = document.createElement('script');
		script.src = src;
		script.async = true;
		script.onload = () => {
			if (window.CDTSGlobeEngine) {
				resolve(window.CDTSGlobeEngine);
			} else {
				reject(new Error('Globe engine did not expose CDTSGlobeEngine'));
			}
		};
		script.onerror = () => {
			enginePromise = null;
			reject(new Error('Globe engine failed to load'));
		};
		document.head.appendChild(script);
	});

	return enginePromise;
}

function prefersReducedMotion() {
	return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function isOnScreen(element) {
	const rect = element.getBoundingClientRect();
	return (
		rect.width > 0 &&
		rect.height > 0 &&
		rect.bottom > 0 &&
		rect.top < (window.innerHeight || 0)
	);
}

function GlobeStage({ engineSrc, label }) {
	const hostRef = useRef(null);
	const [armed, setArmed] = useState(false);

	useEffect(() => {
		const host = hostRef.current;
		if (!host || !engineSrc) return undefined;

		// Start straight away when the globe is already on screen, which covers
		// deep links, a missing IntersectionObserver, and browsers that never
		// deliver the callback. Waiting on the observer would leave it blank.
		if (typeof IntersectionObserver !== 'function' || isOnScreen(host)) {
			setArmed(true);
			return undefined;
		}

		const observer = new IntersectionObserver(
			([entry]) => {
				if (!entry.isIntersecting) return;
				observer.disconnect();
				setArmed(true);
			},
			{ rootMargin: PRELOAD_MARGIN }
		);
		observer.observe(host);

		return () => observer.disconnect();
	}, [engineSrc]);

	useEffect(() => {
		const host = hostRef.current;
		if (!armed || !host) return undefined;

		let globe = null;
		let cancelled = false;

		loadEngine(engineSrc)
			.then((engine) => {
				if (cancelled) return;
				// Throws when the browser has no WebGL, which the catch handles.
				globe = engine.createGlobe({
					container: host,
					label,
					reducedMotion: prefersReducedMotion(),
				});
			})
			.catch(() => {
				if (!cancelled) host.setAttribute('data-globe-state', 'unavailable');
			});

		return () => {
			cancelled = true;
			if (globe) globe.destroy();
		};
	}, [armed, engineSrc, label]);

	return <div className="custom-dubai-globe-section__canvas" ref={hostRef} />;
}

document.querySelectorAll('[data-custom-dubai-globe]').forEach((section) => {
	const mount = section.querySelector('[data-custom-dubai-globe-mount]');
	if (!mount) return;

	createRoot(mount).render(
		<GlobeStage
			engineSrc={section.dataset.engineSrc || ''}
			label={section.dataset.globeLabel || ''}
		/>
	);
});
