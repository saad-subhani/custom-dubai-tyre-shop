/**
 * The seven emirates of the UAE, pinned on the globe.
 *
 * `lat` / `lng` are the three-globe accessor keys and place the gold pin on the
 * map. `id` becomes a BEM modifier on the DOM label, which is how the names get
 * fanned out: the emirates sit within about a degree of each other, so stacking
 * their labels on the pins themselves would be an unreadable pile.
 * `hub` marks Dubai so the engine can give it the pulsing ring.
 */
export const EMIRATES = [
	{ id: 'abu-dhabi', name: 'Abu Dhabi', lat: 24.4539, lng: 54.3773 },
	{ id: 'dubai', name: 'Dubai', lat: 25.2048, lng: 55.2708, hub: true },
	{ id: 'sharjah', name: 'Sharjah', lat: 25.3463, lng: 55.4209 },
	{ id: 'fujairah', name: 'Fujairah', lat: 25.1288, lng: 56.3265 },
	{ id: 'ajman', name: 'Ajman', lat: 25.4052, lng: 55.5136 },
	{ id: 'umm-al-quwain', name: 'Umm Al Quwain', lat: 25.5647, lng: 55.5532 },
	{ id: 'ras-al-khaimah', name: 'Ras Al Khaimah', lat: 25.7895, lng: 55.9432 },
];

/** Only the supply hub gets the ring, the rest stay as plain pins. */
export const HUBS = EMIRATES.filter((emirate) => emirate.hub);
