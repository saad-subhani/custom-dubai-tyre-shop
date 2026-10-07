(function () {
	'use strict';

	var modal = document.querySelector('[data-modal="vehicle"]');
	if (!modal) {
		return;
	}

	var optionsElement = modal.querySelector('[data-options]');
	var selectionElement = modal.querySelector('[data-current-selection]');
	var searchInput = modal.querySelector('[data-vehicle-search]');
	var steps = ['make', 'model', 'year', 'engine'];
	var vehicleData = {
		Acura: ['MDX', 'RDX', 'TLX'],
		'Aston Martin': ['DBX', 'DB11', 'Vantage'],
		Audi: ['A3', 'A4', 'Q5', 'Q7'],
		BAIC: ['BJ40', 'X35', 'X55'],
		BMW: ['3 Series', '5 Series', 'X3', 'X5'],
		Bentley: ['Bentayga', 'Continental GT', 'Flying Spur'],
		BYD: ['Atto 3', 'Han', 'Seal'],
		Chevrolet: ['Captiva', 'Tahoe', 'Traverse'],
		Honda: ['Accord', 'Civic', 'CR-V'],
		Hyundai: ['Accent', 'Elantra', 'Tucson'],
		Kia: ['K5', 'Sportage', 'Sorento'],
		Lexus: ['ES', 'NX', 'RX'],
		Mazda: ['Mazda 3', 'CX-5', 'CX-9'],
		Mercedes: ['C-Class', 'E-Class', 'GLC', 'GLE'],
		Mitsubishi: ['ASX', 'Outlander', 'Pajero'],
		Nissan: ['Altima', 'Patrol', 'Pathfinder', 'X-Trail'],
		Porsche: ['Cayenne', 'Macan', 'Panamera'],
		Subaru: ['Forester', 'Outback', 'WRX'],
		Tesla: ['Model 3', 'Model S', 'Model Y'],
		Toyota: ['Camry', 'Corolla', 'Land Cruiser', 'Prado', 'Yaris']
	};
	var years = ['2026', '2025', '2024', '2023', '2022', '2021', '2020', '2019', '2018', '2017'];
	var engines = ['1.5L Petrol', '1.6L Petrol', '2.0L Petrol', '2.5L Petrol', '3.0L Petrol', 'Hybrid', 'Electric'];
	var selection = {};
	var activeStep = 'make';
	var returnFocus = null;

	function optionsForStep() {
		if (activeStep === 'make') {
			return Object.keys(vehicleData);
		}
		if (activeStep === 'model') {
			return vehicleData[selection.make] || [];
		}
		if (activeStep === 'year') {
			return years;
		}
		return engines;
	}

	function showSelection() {
		var text = [selection.make, selection.model, selection.year, selection.engine].filter(Boolean).join(' / ');
		selectionElement.textContent = text || 'Choose make, model, year and engine';
	}

	function renderOptions() {
		var query = searchInput.value.trim().toLowerCase();
		var options = optionsForStep().filter(function (option) {
			return option.toLowerCase().indexOf(query) !== -1;
		});
		optionsElement.textContent = '';
		if (!options.length) {
			var emptyMessage = document.createElement('p');
			emptyMessage.className = 'custom-dubai-vehicle-modal__empty';
			emptyMessage.textContent = 'No matching options. Try another search.';
			optionsElement.appendChild(emptyMessage);
			return;
		}

		options.forEach(function (value) {
			var button = document.createElement('button');
			button.className = 'custom-dubai-modal__option';
			button.type = 'button';
			button.setAttribute('role', 'option');
			button.setAttribute('data-value', value);
			button.setAttribute('aria-selected', selection[activeStep] === value ? 'true' : 'false');
			button.textContent = value;
			optionsElement.appendChild(button);
		});
	}

	function setStep(step) {
		activeStep = step;
		Array.prototype.forEach.call(modal.querySelectorAll('[data-step]'), function (button) {
			button.setAttribute('aria-selected', button.getAttribute('data-step') === activeStep ? 'true' : 'false');
		});
		searchInput.value = '';
		renderOptions();
	}

	function closeModal() {
		modal.hidden = true;
		document.body.classList.remove('custom-dubai-modal-open');
		if (returnFocus && typeof returnFocus.focus === 'function') {
			returnFocus.focus();
		}
	}

	function openModal() {
		document.dispatchEvent(new CustomEvent('custom-dubai:close-all-modals'));
		returnFocus = document.activeElement;
		modal.hidden = false;
		document.body.classList.add('custom-dubai-modal-open');
		showSelection();
		setStep('make');
		searchInput.focus();
	}

	modal.addEventListener('click', function (event) {
		var closeButton = event.target.closest('[data-modal-close]');
		if (closeButton) {
			closeModal();
			return;
		}

		var stepButton = event.target.closest('[data-step]');
		if (stepButton && steps.indexOf(stepButton.getAttribute('data-step')) <= steps.indexOf(activeStep)) {
			setStep(stepButton.getAttribute('data-step'));
			return;
		}

		var optionButton = event.target.closest('[data-value]');
		if (!optionButton) {
			return;
		}

		selection[activeStep] = optionButton.getAttribute('data-value');
		var nextIndex = steps.indexOf(activeStep) + 1;
		if (activeStep === 'make') {
			delete selection.model;
			delete selection.year;
			delete selection.engine;
		} else if (activeStep === 'model') {
			delete selection.year;
			delete selection.engine;
		} else if (activeStep === 'year') {
			delete selection.engine;
		}
		showSelection();
		if (nextIndex < steps.length) {
			setStep(steps[nextIndex]);
			return;
		}

		document.dispatchEvent(new CustomEvent('custom-dubai:vehicle-selected', { detail: selection }));
		closeModal();
	});

	searchInput.addEventListener('input', renderOptions);
	document.addEventListener('custom-dubai:open-vehicle-modal', openModal);
	document.addEventListener('custom-dubai:close-all-modals', function () {
		modal.hidden = true;
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && !modal.hidden) {
			closeModal();
		}
	});
	setStep('make');
})();
