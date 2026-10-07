/**
 * Globe engine.
 *
 * This module is bundled on its own into
 * shortcodes/globe-section/chunks/globe-engine.js and is only downloaded once
 * the section is about to enter the viewport, which keeps three.js and
 * three-globe (~1 MB) completely out of the critical path.
 *
 * Exposed on the page as `window.CDTSGlobeEngine`.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { CSS2DRenderer } from 'three/examples/jsm/renderers/CSS2DRenderer.js';
import ThreeGlobe from 'three-globe';

import { EMIRATES, HUBS } from './emirates.js';
import UAE from './geo/uae.json';
import WORLD from './geo/world.json';

/**
 * Camera distance from the globe centre. Scrolling down flies the camera in,
 * from a broad view of the whole planet to a close-up on the UAE. The globe
 * itself is always radius 100.
 */
const CAMERA_DISTANCE_FAR = 470;
const CAMERA_DISTANCE_NEAR = 300;
const FIELD_OF_VIEW = 42;
const MAX_PIXEL_RATIO = 1.75;

/**
 * Orientations that put the UAE on the front pole of the globe, dead centre and
 * facing the camera. Derived from three-globe's polar2Cartesian
 * (theta = 90 - lng, Y up) against the UAE centroid at 24.2 N, 54.0 E.
 */
const UAE_ROTATION_Y = -0.94;
const UAE_ROTATION_X = 0.42;

/**
 * Radians the globe sweeps across the scroll. The sweep ends on UAE_ROTATION_Y,
 * so the rotation and the zoom land on the UAE at the same moment.
 */
const SCROLL_SPAN = Math.PI * 1.25;
const IDLE_SPIN = 0.0004;
const EASE = 0.08;

/** Scroll point past which the close-up is far enough in to show the names. */
const ZOOM_LABEL_THRESHOLD = 0.45;

const UAE_FLAG = 'uae';

// UAE National Flag Colors
// Resolved from :root in style.css so the flag stays centrally configurable.
// UAE_FLAG_DEFAULTS are the official national-flag values and act as the
// fallback if a token is ever removed.
const UAE_FLAG_DEFAULTS = {
	green: '#00732F',
	red: '#E8112d',
	redRgb: '232, 17, 45',
	white: '#FFFFFF',
	black: '#111315',
	darkGreen: '#004c1f',
};

const TOKEN_DEFAULTS = {
	'--color-globe-ocean': '#171819',
	'--color-globe-ocean-emissive': '#171819',
	'--color-globe-land': '#c8cacc',
	'--color-globe-highlight': UAE_FLAG_DEFAULTS.green,
	'--color-globe-highlight-edge': UAE_FLAG_DEFAULTS.red,
	'--color-globe-pin': UAE_FLAG_DEFAULTS.red,
	'--color-globe-atmosphere': '#d9dcde',
	'--color-flag-uae-green': UAE_FLAG_DEFAULTS.green,
	'--color-flag-uae-red': UAE_FLAG_DEFAULTS.red,
	'--color-flag-uae-red-rgb': UAE_FLAG_DEFAULTS.redRgb,
	'--color-flag-uae-white': UAE_FLAG_DEFAULTS.white,
	'--color-flag-uae-black': UAE_FLAG_DEFAULTS.black,
	'--color-flag-uae-dark-green': UAE_FLAG_DEFAULTS.darkGreen,
	'--color-accent': '#d5a51c',
	'--color-globe-light': '#ffffff',
};

/**
 * Flag + globe colours resolved once at init. `createPinElement` is handed to
 * three-globe as a bare callback and has no access to the local `tokens`
 * binding, so it reads this module-level snapshot instead.
 */
let activeTokens = null;

const FEATURES = [
	...WORLD.features,
	{ ...UAE, properties: { ...UAE.properties, [UAE_FLAG]: true } },
];

const isUae = (datum) => Boolean(datum && datum.properties && datum.properties[UAE_FLAG]);

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

function readTokens(element) {
	const styles = getComputedStyle(element);
	const get = (name) => styles.getPropertyValue(name).trim() || TOKEN_DEFAULTS[name];
	return {
		ocean: get('--color-globe-ocean'),
		oceanEmissive: get('--color-globe-ocean-emissive'),
		land: get('--color-globe-land'),
		pin: get('--color-globe-pin'),
		atmosphere: get('--color-globe-atmosphere'),
		accent: get('--color-accent'),
		flagGreen: get('--color-flag-uae-green'),
		flagRed: get('--color-flag-uae-red'),
		flagRedRgb: get('--color-flag-uae-red-rgb'),
		flagWhite: get('--color-flag-uae-white'),
		flagBlack: get('--color-flag-uae-black'),
		flagDarkGreen: get('--color-flag-uae-dark-green'),
		light: get('--color-globe-light'),
	};
}

/** Build the DOM node for one emirate pinned SVG with UAE flag styling. */
function createPinElement(datum) {
	const t = activeTokens || readTokens(document.documentElement);
	const element = document.createElement('div');
	element.className = `custom-dubai-globe-section__pin custom-dubai-globe-section__pin--${datum.id}${datum.hub ? ' custom-dubai-globe-section__pin--hub' : ''}`;
	element.innerHTML = `
		<div class="custom-dubai-globe-section__pin-anchor" aria-hidden="true">
			<svg viewBox="0 0 24 32" class="custom-dubai-globe-section__pin-svg">
				<path d="M12 0C5.373 0 0 5.373 0 12c0 8.5 10.5 18.5 11.3 19.3.4.4 1 .4 1.4 0C13.5 30.5 24 20.5 24 12 24 5.373 18.627 0 12 0z" fill="${datum.hub ? t.flagRed : t.flagGreen}" />
				<circle cx="12" cy="11" r="5.5" fill="${t.flagWhite}" />
				<circle cx="12" cy="11" r="3.2" fill="${datum.hub ? t.accent : t.flagRed}" />
			</svg>
			<span class="custom-dubai-globe-section__pin-pulse"></span>
		</div>
		<div class="custom-dubai-globe-section__pin-card">
			<span class="custom-dubai-globe-section__pin-flag" aria-hidden="true">
				<span class="flag-red"></span>
				<span class="flag-stripes">
					<span class="flag-green"></span>
					<span class="flag-white"></span>
					<span class="flag-black"></span>
				</span>
			</span>
			<span class="custom-dubai-globe-section__pin-text">${datum.name}</span>
			${datum.hub ? '<span class="custom-dubai-globe-section__pin-hub-tag">Hub</span>' : ''}
		</div>
	`;
	return element;
}

/** Release every GPU resource hanging off the scene. */
function disposeScene(scene) {
	scene.traverse((object) => {
		if (object.geometry && typeof object.geometry.dispose === 'function') {
			object.geometry.dispose();
		}
		const material = object.material;
		if (!material) return;
		for (const entry of Array.isArray(material) ? material : [material]) {
			if (!entry) continue;
			for (const value of Object.values(entry)) {
				if (value && value.isTexture) value.dispose();
			}
			entry.dispose();
		}
	});
}

/**
 * Build the globe inside `container`.
 *
 * @param {Object}  options
 * @param {Element} options.container      Sized host element, owns the canvas.
 * @param {string}  options.label          Accessible name for the canvas.
 * @param {boolean} options.reducedMotion  Stop the idle spin and the easing.
 * @returns {{ destroy: () => void }}
 */
export function createGlobe({ container, label = '', reducedMotion = false } = {}) {
	if (!container) throw new Error('createGlobe: a container element is required');

	const tokens = readTokens(container);
	// Publish for createPinElement, which three-globe invokes as a bare callback.
	activeTokens = tokens;
	const scene = new THREE.Scene();
	const camera = new THREE.PerspectiveCamera(FIELD_OF_VIEW, 1, 1, 1400);
	camera.position.set(0, 0, CAMERA_DISTANCE_FAR);

	const renderer = new THREE.WebGLRenderer({
		antialias: true,
		alpha: true,
		powerPreference: 'high-performance',
	});
	renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO));
	renderer.outputColorSpace = THREE.SRGBColorSpace;
	renderer.domElement.setAttribute('role', 'img');
	if (label) renderer.domElement.setAttribute('aria-label', label);
	container.appendChild(renderer.domElement);

	// CSS2DRenderer for projecting interactive SVG pinned labels onto the globe
	const labelRenderer = new CSS2DRenderer();
	labelRenderer.domElement.className = 'custom-dubai-globe-section__labels';
	container.appendChild(labelRenderer.domElement);

	const globe = new ThreeGlobe({ waitForGlobeReady: true, animateIn: true })
		.showAtmosphere(true)
		.atmosphereColor(tokens.atmosphere)
		.atmosphereAltitude(0.2)
		.polygonsData(FEATURES)
		.polygonCapCurvatureResolution(5)
		// Highlight UAE in UAE National Green with Red border and elevated 3D relief
		.polygonAltitude((datum) => (isUae(datum) ? 0.018 : 0.005))
		.polygonCapColor((datum) => (isUae(datum) ? tokens.flagGreen : tokens.land))
		.polygonSideColor((datum) => (isUae(datum) ? tokens.flagDarkGreen : tokens.land))
		.polygonStrokeColor((datum) => (isUae(datum) ? tokens.flagRed : null))
		.pointsData(EMIRATES)
		.pointColor((d) => (d.hub ? tokens.flagRed : tokens.flagGreen))
		.pointRadius(0.35)
		.pointAltitude(0.02)
		.pointResolution(16)
		.pointsTransitionDuration(0)
		.htmlElementsData(EMIRATES)
		.htmlElement(createPinElement)
		.htmlAltitude(0.005)
		.htmlTransitionDuration(0)
		.ringsData(HUBS)
		.ringColor(() => (t) => `rgba(${tokens.flagRedRgb}, ${Math.max(0, 1 - t)})`)
		.ringMaxRadius(4.2)
		.ringPropagationSpeed(2.2)
		.ringRepeatPeriod(1500)
		.ringAltitude(0.012);

	const sphere = globe.globeMaterial();
	sphere.color = new THREE.Color(tokens.ocean);
	sphere.emissive = new THREE.Color(tokens.oceanEmissive);
	sphere.emissiveIntensity = 1;
	sphere.shininess = 0.4;

	scene.add(new THREE.AmbientLight(tokens.light, 1.15));
	const key = new THREE.DirectionalLight(tokens.light, 0.9);
	key.position.set(-2, 1, 1.2);
	scene.add(key, globe);

	globe.rotation.x = UAE_ROTATION_X;

	const controls = new OrbitControls(camera, renderer.domElement);
	controls.enableDamping = true;
	controls.dampingFactor = 0.08;
	controls.rotateSpeed = 0.6;
	controls.enableZoom = false;
	controls.enablePan = false;
	controls.minPolarAngle = 0.4;
	controls.maxPolarAngle = Math.PI - 0.4;

	const resize = () => {
		const { width, height } = container.getBoundingClientRect();
		if (!width || !height) return;
		renderer.setSize(width, height, false);
		labelRenderer.setSize(width, height);
		camera.aspect = width / height;
		camera.updateProjectionMatrix();
	};
	resize();

	const resizeObserver = new ResizeObserver(resize);
	resizeObserver.observe(container);

	let targetSpin = UAE_ROTATION_Y - SCROLL_SPAN;
	let spin = targetSpin;
	let targetDistance = CAMERA_DISTANCE_FAR;
	let distance = targetDistance;
	let lastT = -1;
	let onScreen = true;
	let frameId = 0;

	const frame = () => {
		frameId = requestAnimationFrame(frame);
		if (!onScreen || document.hidden) return;

		const rect = container.getBoundingClientRect();
		const viewport = window.innerHeight || 1;
		const offset = (rect.top + rect.height / 2 - viewport / 2) / (viewport / 2 + rect.height / 2);
		const t = clamp(1 - offset, 0, 1);

		if (t !== lastT) {
			lastT = t;
			targetSpin = UAE_ROTATION_Y + (t - 1) * SCROLL_SPAN;
			targetDistance = CAMERA_DISTANCE_FAR + (CAMERA_DISTANCE_NEAR - CAMERA_DISTANCE_FAR) * t;
			container.dataset.globeZoom = t > ZOOM_LABEL_THRESHOLD ? 'near' : 'far';
		}

		if (reducedMotion) {
			spin = targetSpin;
			distance = targetDistance;
		} else {
			spin += (targetSpin - spin) * EASE;
			distance += (targetDistance - distance) * EASE;
			spin += IDLE_SPIN;
		}

		globe.rotation.y = spin;
		camera.position.setLength(distance);
		controls.update();
		renderer.render(scene, camera);
		labelRenderer.render(scene, camera);
	};
	frameId = requestAnimationFrame(frame);

	const visibilityObserver = new IntersectionObserver(([entry]) => {
		onScreen = entry.isIntersecting;
	});
	visibilityObserver.observe(container);

	const destroy = () => {
		cancelAnimationFrame(frameId);
		resizeObserver.disconnect();
		visibilityObserver.disconnect();
		controls.dispose();
		if (typeof globe._destructor === 'function') globe._destructor();
		disposeScene(scene);
		renderer.dispose();
		labelRenderer.domElement.remove();
		if (renderer.domElement.parentNode) {
			renderer.domElement.parentNode.removeChild(renderer.domElement);
		}
	};

	return { destroy };
}
