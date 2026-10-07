import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ArrowUpRight, ChevronDown, Info } from 'lucide-react';

function Hero({ carImage }) {
	const heroRef = useRef(null);
	const [activeTab, setActiveTab] = useState('tyres');
	const [tyre, setTyre] = useState({ width: '', height: '', rim: '' });
	const [vehicle, setVehicle] = useState({ make: '', model: '', year: '' });

	useEffect(() => {
		const updateTyre = (event) => setTyre((current) => ({ ...current, ...event.detail }));
		const updateVehicle = (event) => setVehicle((current) => ({ ...current, ...event.detail }));
		document.addEventListener('custom-dubai:tyre-selected', updateTyre);
		document.addEventListener('custom-dubai:vehicle-selected', updateVehicle);
		return () => {
			document.removeEventListener('custom-dubai:tyre-selected', updateTyre);
			document.removeEventListener('custom-dubai:vehicle-selected', updateVehicle);
		};
	}, []);

	useEffect(() => {
		const hero = heroRef.current;
		const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
		const pointer = window.matchMedia('(pointer: fine)');
		let frame;
		const reset = () => {
			cancelAnimationFrame(frame);
			hero.style.setProperty('--car-x', '0px');
			hero.style.setProperty('--car-y', '0px');
		};
		const move = (event) => {
			if (motion.matches || !pointer.matches) return;
			const bounds = hero.getBoundingClientRect();
			const x = ((event.clientX - bounds.left) / bounds.width - 0.5) * -26;
			const y = ((event.clientY - bounds.top) / bounds.height - 0.5) * -12;
			cancelAnimationFrame(frame);
			frame = requestAnimationFrame(() => {
				hero.style.setProperty('--car-x', `${x}px`);
				hero.style.setProperty('--car-y', `${y}px`);
			});
		};
		hero.addEventListener('pointermove', move);
		hero.addEventListener('pointerleave', reset);
		motion.addEventListener('change', reset);
		return () => {
			reset();
			hero.removeEventListener('pointermove', move);
			hero.removeEventListener('pointerleave', reset);
			motion.removeEventListener('change', reset);
		};
	}, []);

	const openModal = () => document.dispatchEvent(new CustomEvent(
		activeTab === 'tyres' ? 'custom-dubai:open-tyre-modal' : 'custom-dubai:open-vehicle-modal'
	));
	const values = activeTab === 'tyres' ? tyre : vehicle;
	const fields = activeTab === 'tyres'
		? [['width', 'Width'], ['height', 'Height'], ['rim', 'Rim']]
		: [['make', 'Make'], ['model', 'Model'], ['year', 'Year']];

	return (
		<section ref={heroRef} className="custom-dubai-hero" aria-label="Find tyres for your vehicle">
			<div className="custom-dubai-hero__visual">
			<div className="custom-dubai-hero__glow" aria-hidden="true" />
			<div className="custom-dubai-hero__copy">
				<p className="custom-dubai-hero__eyebrow"><span /> Dubai & Abu Dhabi</p>
				<h1 aria-label="Premium Tyres For Every Journey."><span className="custom-dubai-hero__title-line"><span>Premium Tyres For</span></span><span className="custom-dubai-hero__title-line"><span>Every Journey.</span></span></h1>
				<p className="custom-dubai-hero__intro">Find the right tyres for your vehicle. Drive with confidence.</p>
				<button className="custom-dubai-hero__browse" type="button" onClick={openModal}>Find my tyres <ArrowUpRight aria-hidden="true" /></button>
			</div>
			<div className="custom-dubai-hero__callout"><span aria-hidden="true" /><p>Confidence on the road.<br />It starts with the right tyres.</p></div>
			<div className="custom-dubai-hero__stage" aria-hidden="true">
				<div className="custom-dubai-hero__floor" />
				<div className="custom-dubai-hero__drive"><img className="custom-dubai-hero__car" src={carImage} width="1536" height="1024" alt="" fetchPriority="high" /></div>
			</div>
			<div className="custom-dubai-hero__benefits" aria-label="Our focus">
				<div><span>The right fit</span><strong>Precision</strong></div>
				<div><span>On every road</span><strong>Confidence</strong></div>
				<div><span>Every journey</span><strong>Comfort</strong></div>
			</div>
			</div>
			<form className="custom-dubai-hero__search" onSubmit={(event) => { event.preventDefault(); openModal(); }}>
				<div className="custom-dubai-hero__search-top">
					<div className="custom-dubai-hero__search-mode"><span>Search by</span><div className="custom-dubai-hero__tabs" role="group" aria-label="Search by">
						{['tyres', 'vehicle'].map((tab) => <button key={tab} className="custom-dubai-hero__tab" type="button" aria-pressed={activeTab === tab} onClick={() => setActiveTab(tab)}>{tab === 'vehicle' ? 'Vehicle' : 'Tyre size'}</button>)}
					</div></div>
				</div>
				<div className="custom-dubai-hero__search-bottom">
					<div className="custom-dubai-hero__fields">{fields.map(([key, label]) => <div className="custom-dubai-hero__field-wrap" key={key}><span aria-hidden="true">{label}</span><button className="custom-dubai-hero__field" type="button" aria-label={`${label}: ${values[key] || 'Choose ' + label.toLowerCase()}`} onClick={openModal}><span>{values[key] || label}</span><ChevronDown aria-hidden="true" /></button></div>)}</div>
					<button className="custom-dubai-hero__submit" type="submit">Find tyres <ArrowUpRight aria-hidden="true" /></button>
				</div>
				<button className="custom-dubai-hero__help" type="button" onClick={openModal}><Info aria-hidden="true" /><span>{activeTab === 'tyres' ? 'Learn to read your tyre size here' : 'Help me find my vehicle'}</span></button>
			</form>
		</section>
	);
}

document.querySelectorAll('.custom-dubai-hero-root').forEach((element) => {
	createRoot(element).render(<Hero carImage={element.dataset.carImage} />);
});
