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
 * itself is always radius 100. Keep the close view far enough away to preserve
 * the whole spherical silhouette while the camera moves toward the UAE.
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

/**
 * Every colour comes from a global design token so there is nothing to keep in
 * sync with style.css. Hex is the only notation read from the custom property.
 */
const TOKEN_DEFAULTS = {
	'--color-globe-ocean': '#171819',
	'--color-globe-ocean-emissive': '#171819',
	'--color-globe-land': '#c8cacc',
	'--color-globe-highlight': '#f0f1f2',
	'--color-globe-highlight-edge': '#ffffff',
	'--color-globe-pin': '#ffffff',
	'--color-globe-atmosphere': '#d9dcde',
};

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
		uae: get('--color-globe-highlight'),
		uaeEdge: get('--color-globe-highlight-edge'),
	};
}

/** Build the DOM node for one emirate name. Styling lives in the component CSS. */
function createPinElement(datum) {
	const element = document.createElement('span');
	element.className = `custom-dubai-globe-section__pin custom-dubai-globe-section__pin--${datum.id}`;
	element.textContent = datum.name;
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

	// three-globe projects these names onto CSS2DObjects but leaves rendering
	// them to the host, so the section supplies its own overlay layer.
	const labelRenderer = new CSS2DRenderer();
	labelRenderer.domElement.className = 'custom-dubai-globe-section__labels';
	container.appendChild(labelRenderer.domElement);

	const globe = new ThreeGlobe({ waitForGlobeReady: true, animateIn: true })
		.showAtmosphere(true)
		.atmosphereColor(tokens.atmosphere)
		.atmosphereAltitude(0.2)
		.polygonsData(FEATURES)
		.polygonCapCurvatureResolution(5)
		.polygonAltitude((datum) => (isUae(datum) ? 0.012 : 0.005))
		.polygonCapColor((datum) => (isUae(datum) ? tokens.uae : tokens.land))
		.polygonSideColor(() => tokens.land)
		// A falsy stroke tells three-globe to skip the outline entirely, so the
		// only country that gets a border is the one we want to highlight.
		.polygonStrokeColor((datum) => (isUae(datum) ? tokens.uaeEdge : null))
		.pointsData(EMIRATES)
		.pointColor(() => tokens.uaeEdge)
		.pointRadius(0.26)
		.pointAltitude(0.03)
		.pointResolution(16)
		.pointsTransitionDuration(0)
		// The names are real DOM nodes, so the stylesheet can fan them out
		// instead of letting seven labels stack on the same few pixels.
		.htmlElementsData(EMIRATES)
		.htmlElement(createPinElement)
		.htmlAltitude(0.004)
		.htmlTransitionDuration(0)
		.ringsData(HUBS)
		.ringColor(() => tokens.uaeEdge)
		.ringMaxRadius(3.4)
		.ringPropagationSpeed(2.2)
		.ringRepeatPeriod(1600)
		.ringAltitude(0.01);

	const sphere = globe.globeMaterial();
	sphere.color = new THREE.Color(tokens.ocean);
	sphere.emissive = new THREE.Color(tokens.oceanEmissive);
	sphere.emissiveIntensity = 1;
	sphere.shininess = 0.4;

	scene.add(new THREE.AmbientLight(0xffffff, 1.1));
	const key = new THREE.DirectionalLight(0xffffff, 0.85);
	key.position.set(-2, 1, 1.2);
	scene.add(key, globe);

	globe.rotation.x = UAE_ROTATION_X;

	const controls = new OrbitControls(camera, renderer.domElement);
	controls.enableDamping = true;
	controls.dampingFactor = 0.08;
	controls.rotateSpeed = 0.6;
	// Zooming would swallow the wheel event and break page scrolling, which is
	// what drives the rotation here.
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

	// Scroll drives both the rotation and the camera distance. `t` is 0 while
	// the globe is still arriving at the bottom of the viewport and 1 once the
	// section is centred, so the whole move happens while it is actually on
	// screen. Measuring against the element centre keeps this independent of
	// how tall the section or viewport happens to be.
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
			// The stylesheet hides the emirate names until the close-up starts,
			// so they never clutter the wide view of the planet.
			container.dataset.globeZoom = t > ZOOM_LABEL_THRESHOLD ? 'near' : 'far';
		}

		if (reducedMotion) {
			// Visitors who ask for less motion get no animation at all: the
			// globe stays locked to the scroll position instead of sweeping.
			spin = targetSpin;
			distance = targetDistance;
		} else {
			spin += (targetSpin - spin) * EASE;
			distance += (targetDistance - distance) * EASE;
			spin += IDLE_SPIN;
		}

		globe.rotation.y = spin;
		// Setting the length keeps the angle the visitor dragged the globe to.
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
