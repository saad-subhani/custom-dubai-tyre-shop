import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Info } from 'lucide-react';

const SLIDES = [0, 1, 2];

function Hero() {
	const [activeTab, setActiveTab] = useState('tyres');
	const [activeSlide, setActiveSlide] = useState(0);
	const [tyre, setTyre] = useState({ width: '', height: '', rim: '' });
	const [vehicle, setVehicle] = useState({ make: '', model: '', year: '' });

	useEffect(() => {
		const updateTyre = (event) => {
			const selection = event.detail || {};
			setTyre((current) => ({ ...current, ...selection }));
		};
		const updateVehicle = (event) => {
			const selection = event.detail || {};
			setVehicle((current) => ({ ...current, ...selection }));
		};

		document.addEventListener('custom-dubai:tyre-selected', updateTyre);
		document.addEventListener('custom-dubai:vehicle-selected', updateVehicle);
		return () => {
			document.removeEventListener('custom-dubai:tyre-selected', updateTyre);
			document.removeEventListener('custom-dubai:vehicle-selected', updateVehicle);
		};
	}, []);

	const openModal = (tab = activeTab) => {
		document.dispatchEvent(new CustomEvent(
			tab === 'tyres' ? 'custom-dubai:open-tyre-modal' : 'custom-dubai:open-vehicle-modal'
		));
	};

	const values = activeTab === 'tyres' ? tyre : vehicle;
	const setValues = activeTab === 'tyres' ? setTyre : setVehicle;
	const fields = activeTab === 'tyres'
		? [['width', 'Width'], ['height', 'Height'], ['rim', 'Rim']]
		: [['make', 'Make'], ['model', 'Model'], ['year', 'Year']];

	return (
		<section className="custom-dubai-hero" aria-label="Tyre and vehicle search" data-slide={activeSlide}>
			<div className="custom-dubai-hero__scene" aria-hidden="true">
				<div className="custom-dubai-hero__wheel" />
				<div className="custom-dubai-hero__vehicle" />
			</div>
			<div className="custom-dubai-hero__copy">
				<p>Dubai & Abu Dhabi</p>
				<h1>Buy Tyres Online</h1>
				<span>Find the right fit for your vehicle</span>
			</div>

			<form className="custom-dubai-hero__search" onSubmit={(event) => event.preventDefault()}>
				<div className="custom-dubai-hero__tabs" role="tablist" aria-label="Search by">
					{['tyres', 'vehicle'].map((tab) => (
						<button
							key={tab}
							className="custom-dubai-hero__tab"
							type="button"
							role="tab"
							aria-selected={activeTab === tab}
							onClick={() => setActiveTab(tab)}
						>
							{tab === 'vehicle' ? 'Vehicle' : 'Tyres'}
						</button>
					))}
				</div>

				<div className="custom-dubai-hero__fields">
					{fields.map(([key, label]) => (
						<button
							key={key}
							className="custom-dubai-hero__field"
							type="button"
							aria-label={`${label}${values[key] ? `: ${values[key]}` : ''}`}
							onClick={() => openModal(activeTab)}
						>
							{values[key] || label}
						</button>
					))}
				</div>

				<button className="custom-dubai-hero__help" type="button" onClick={openModal} aria-label="Open selection guide">
					<Info aria-hidden="true" focusable="false" />
					<span>{activeTab === 'tyres' ? 'Learn to read your tyre size here' : 'Help me find my vehicle'}</span>
				</button>
			</form>

			<div className="custom-dubai-hero__scroller" role="group" aria-label="Hero slides">
				{SLIDES.map((slide) => (
					<button
						key={slide}
						className="custom-dubai-hero__dot"
						type="button"
						aria-label={`Show slide ${slide + 1}`}
						aria-current={activeSlide === slide ? 'true' : undefined}
						onClick={() => setActiveSlide(slide)}
					/>
				))}
			</div>
		</section>
	);
}

document.querySelectorAll('.custom-dubai-hero-root').forEach((element) => {
	createRoot(element).render(<Hero />);
});
