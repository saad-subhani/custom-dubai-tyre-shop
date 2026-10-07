/**
 * Converts the Natural Earth 110m TopoJSON source into the two compact
 * GeoJSON payloads the globe engine actually ships to the browser.
 *
 *   geo/uae.json    the UAE outline, full precision (the only highlighted land)
 *   geo/world.json  every other country, simplified and island-stripped so the
 *                   lazy globe chunk stays as small as possible
 *
 * Run after replacing the source with:
 *   npm run prepare:globe-geo
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const SOURCE = join(HERE, 'source', 'countries-110m.json');
const OUT_DIR = join(HERE, 'geo');

const UAE_ID = '784';
const ANTARCTICA_ID = '010';
const MIN_LAND_AREA = 0.35; // square degrees, drops specks that are sub-pixel on the globe
const WORLD_PRECISION = 1; // ~11 km, invisible at globe scale and much smaller
const UAE_PRECISION = 2; // ~1 km, the highlighted border needs the detail

/** Expand the delta-encoded, quantised TopoJSON arcs into absolute lon/lat pairs. */
function decodeArcs(topology) {
	const { scale: [sx, sy], translate: [tx, ty] } = topology.transform;
	return topology.arcs.map((arc) => {
		let x = 0;
		let y = 0;
		return arc.map(([dx, dy]) => {
			x += dx;
			y += dy;
			return [x * sx + tx, y * sy + ty];
		});
	});
}

/** Stitch one TopoJSON ring (a list of arc indexes) into a single coordinate list. */
function buildRing(indexes, arcs) {
	const ring = [];
	for (const index of indexes) {
		const arc = index < 0 ? arcs[~index].slice().reverse() : arcs[index];
		for (let i = ring.length ? 1 : 0; i < arc.length; i += 1) {
			ring.push(arc[i]);
		}
	}
	return ring;
}

/** Signed-area magnitude of a lon/lat ring, in square degrees. */
function ringArea(ring) {
	let sum = 0;
	for (let i = 0, j = ring.length - 1; i < ring.length; j = i, i += 1) {
		sum += (ring[j][0] - ring[i][0]) * (ring[j][1] + ring[i][1]);
	}
	return Math.abs(sum / 2);
}

/** Round coordinates and drop duplicates, which is where most of the bytes go. */
function compact(ring, precision) {
	const factor = 10 ** precision;
	const out = [];
	let prevX = null;
	let prevY = null;
	for (const [lng, lat] of ring) {
		const x = Math.round(lng * factor) / factor;
		const y = Math.round(lat * factor) / factor;
		if (x === prevX && y === prevY) continue;
		prevX = x;
		prevY = y;
		out.push([x, y]);
	}
	// Rounding can drop a point that used to close the ring. earcut, which
	// three-globe triangulates with, throws unless first and last match.
	if (out.length > 1) {
		const [firstX, firstY] = out[0];
		const [lastX, lastY] = out[out.length - 1];
		if (firstX !== lastX || firstY !== lastY) out.push([firstX, firstY]);
	}
	return out;
}

/**
 * Turn one TopoJSON geometry into `{ outer, holes }` polygons, filtered by area
 * and rounded to `precision`. Returns an empty list when nothing survives.
 */
function polygonsOf(geometry, arcs, precision, minArea) {
	const groups = geometry.type === 'MultiPolygon' ? geometry.arcs : [geometry.arcs];
	const polygons = [];

	for (const rings of groups) {
		const [outer, ...holes] = rings.map((ring) => compact(buildRing(ring, arcs), precision));
		if (outer.length < 4 || ringArea(outer) < minArea) continue;
		polygons.push([outer, ...holes.filter((hole) => hole.length >= 4 && ringArea(hole) >= minArea)]);
	}

	return polygons;
}

/** Build the GeoJSON geometry for a list of `[outer, ...holes]` polygons. */
function geometryOf(polygons) {
	if (!polygons.length) return null;
	if (polygons.length === 1) {
		return { type: 'Polygon', coordinates: polygons[0] };
	}
	return { type: 'MultiPolygon', coordinates: polygons };
}

function readSource() {
	return JSON.parse(readFileSync(SOURCE, 'utf8'));
}

function main() {
	const topology = readSource();
	const arcs = decodeArcs(topology);
	const geometries = topology.objects.countries.geometries;

	const world = [];
	let uae = null;

	for (const geometry of geometries) {
		const id = String(geometry.id);

		if (id === ANTARCTICA_ID) continue; // wraps the south pole and only adds bytes

		if (id === UAE_ID) {
			uae = {
				type: 'Feature',
				id: UAE_ID,
				properties: { name: geometry.properties.name },
				geometry: geometryOf(polygonsOf(geometry, arcs, UAE_PRECISION, 0.001)),
			};
			continue;
		}

		const geometryOut = geometryOf(polygonsOf(geometry, arcs, WORLD_PRECISION, MIN_LAND_AREA));
		if (!geometryOut) continue;
		world.push({
			type: 'Feature',
			id,
			properties: { name: geometry.properties.name },
			geometry: geometryOut,
		});
	}

	if (!uae) throw new Error(`UAE (id ${UAE_ID}) not found in ${SOURCE}`);

	mkdirSync(OUT_DIR, { recursive: true });
	writeFileSync(join(OUT_DIR, 'uae.json'), JSON.stringify(uae));
	writeFileSync(
		join(OUT_DIR, 'world.json'),
		JSON.stringify({ type: 'FeatureCollection', features: world })
	);

	const kb = (name) => Math.round(readFileSync(join(OUT_DIR, name)).length / 1024);
	console.log(`uae.json   ${kb('uae.json')} KB  (${uae.geometry.type})`);
	console.log(`world.json ${kb('world.json')} KB  (${world.length} countries)`);
}

main();
