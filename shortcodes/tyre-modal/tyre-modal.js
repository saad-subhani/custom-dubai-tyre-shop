(function () {
	'use strict';

	var modal = document.querySelector('[data-modal="tyre"]');
	if (!modal) {
		return;
	}

	var optionsElement = modal.querySelector('[data-options]');
	var selectionElement = modal.querySelector('[data-current-selection]');
	var heightStepButton = modal.querySelector('[data-step="height"]');
	var defaultHeightLabel = heightStepButton ? heightStepButton.textContent : 'Height';
	var steps = ['width', 'height', 'rim'];

	// All width-height-rim combinations
	var TYRE_COMBINATIONS = {
		'155': {
			heights: ['none', '70'],
			rims: {
				'none': ['R12C'],
				'70': ['R13']
			}
		},
		'165': {
			heights: ['none', '50', '60', '65', '70'],
			rims: {
				'none': ['R13C', 'R14C'],
				'50': ['R15'],
				'60': ['R14'],
				'65': ['R13', 'R14'],
				'70': ['R13', 'R14']
			}
		},
		'175': {
			heights: ['none', '50', '55', '60', '65', '70'],
			rims: {
				'none': ['R13C'],
				'50': ['R15', 'R16'],
				'55': ['R15'],
				'60': ['R13', 'R14', 'R15'],
				'65': ['R14', 'R15'],
				'70': ['R13', 'R14', 'R18']
			}
		},
		'185': {
			heights: ['none', '55', '60', '65', '70', '75'],
			rims: {
				'none': ['R14C'],
				'55': ['R14', 'R15', 'R16'],
				'60': ['R13', 'R14', 'R15'],
				'65': ['R14', 'R15'],
				'70': ['R13', 'R14', 'R15'],
				'75': ['R16']
			}
		},
		'195': {
			heights: ['none', '45', '50', '55', '60', '65', '70', '75', '80'],
			rims: {
				'none': ['R14C', 'R15C'],
				'45': ['R16', 'R17'],
				'50': ['R15', 'R16', 'R18'],
				'55': ['R15', 'R16'],
				'60': ['R14', 'R15', 'R16'],
				'65': ['R15', 'R16'],
				'70': ['R14', 'R15C'],
				'75': ['R16', 'R16C'],
				'80': ['R15']
			}
		},
		'205': {
			heights: ['none', '40', '45', '50', '55', '60', '65', '70', '75', '80'],
			rims: {
				'none': ['R16C'],
				'40': ['R17', 'R18'],
				'45': ['R16', 'R17'],
				'50': ['R15', 'R16', 'R17'],
				'55': ['R15', 'R16', 'R17'],
				'60': ['R14', 'R15', 'R16', 'R17'],
				'65': ['R15', 'R16', 'R16C'],
				'70': ['R15', 'R15C', 'R16'],
				'75': ['R16C'],
				'80': ['R16']
			}
		},
		'215': {
			heights: ['none', '35', '40', '45', '50', '55', '60', '65', '70', '75', '85'],
			rims: {
				'none': ['R15C'],
				'35': ['R18'],
				'40': ['R17', 'R18'],
				'45': ['R16', 'R17', 'R18', 'R20'],
				'50': ['R17', 'R18', 'R19'],
				'55': ['R16', 'R17', 'R18'],
				'60': ['R15', 'R16', 'R17'],
				'65': ['R15', 'R16', 'R16C', 'R17', 'R17C'],
				'70': ['R15', 'R15C', 'R16', 'R16C'],
				'75': ['R15'],
				'85': ['R16']
			}
		},
		'225': {
			heights: ['35', '40', '45', '50', '55', '60', '65', '70', '75'],
			rims: {
				'35': ['R18', 'R19', 'R20'],
				'40': ['R18', 'R19', 'R20'],
				'45': ['R15', 'R17', 'R18', 'R19', 'R21'],
				'50': ['R15', 'R16', 'R17', 'R18', 'R19'],
				'55': ['R16', 'R17', 'R18', 'R19'],
				'60': ['R15', 'R16', 'R17', 'R18'],
				'65': ['R16', 'R16C', 'R17', 'R18'],
				'70': ['R15', 'R15C', 'R16', 'R17', 'R17C'],
				'75': ['R15', 'R16', 'R16C']
			}
		},
		'235': {
			heights: ['30', '35', '40', '45', '50', '55', '60', '65', '70', '75', '80', '85'],
			rims: {
				'30': ['R20', 'R22'],
				'35': ['R19', 'R20', 'R21'],
				'40': ['R17', 'R18', 'R19', 'R20'],
				'45': ['R13', 'R17', 'R18', 'R19', 'R20', 'R21'],
				'50': ['R17', 'R18', 'R19', 'R20', 'R21'],
				'55': ['R17', 'R18', 'R19', 'R20'],
				'60': ['R14', 'R15', 'R16', 'R17', 'R18', 'R19', 'R20'],
				'65': ['R16', 'R16C', 'R17', 'R18', 'R19'],
				'70': ['R16'],
				'75': ['R15'],
				'80': ['R17', 'R18'],
				'85': ['R16']
			}
		},
		'245': {
			heights: ['30', '35', '40', '45', '50', '55', '60', '65', '70', '75'],
			rims: {
				'30': ['R19', 'R20', 'R21'],
				'35': ['R15', 'R18', 'R19', 'R20', 'R21'],
				'40': ['R15', 'R17', 'R18', 'R19', 'R20', 'R21'],
				'45': ['R17', 'R18', 'R19', 'R20', 'R21'],
				'50': ['R17', 'R18', 'R19', 'R20'],
				'55': ['R17', 'R18', 'R19', 'R20'],
				'60': ['R14', 'R15', 'R18', 'R20'],
				'65': ['R17'],
				'70': ['R16', 'R17', 'R18'],
				'75': ['R16', 'R17']
			}
		},
		'255': {
			heights: ['30', '35', '40', '45', '50', '55', '60', '65', '70'],
			rims: {
				'30': ['R19', 'R20', 'R22', 'R24'],
				'35': ['R18', 'R19', 'R20', 'R21', 'R22'],
				'40': ['R17', 'R18', 'R19', 'R20', 'R21', 'R22'],
				'45': ['R17', 'R18', 'R19', 'R20', 'R21', 'R22'],
				'50': ['R18', 'R19', 'R20', 'R21'],
				'55': ['R18', 'R19', 'R20'],
				'60': ['R15', 'R17', 'R18', 'R19', 'R20'],
				'65': ['R16', 'R17', 'R18', 'R19'],
				'70': ['R15', 'R15C', 'R16', 'R17', 'R18']
			}
		},
		'265': {
			heights: ['30', '35', '40', '45', '50', '55', '60', '65', '70', '75'],
			rims: {
				'30': ['R19', 'R20', 'R22'],
				'35': ['R18', 'R19', 'R20', 'R21', 'R22'],
				'40': ['R18', 'R19', 'R20', 'R21', 'R22'],
				'45': ['R18', 'R19', 'R20', 'R21'],
				'50': ['R15', 'R19', 'R20', 'R22'],
				'55': ['R19', 'R20'],
				'60': ['R18', 'R20'],
				'65': ['R17', 'R18'],
				'70': ['R15', 'R16', 'R16C', 'R17', 'R18'],
				'75': ['R16']
			}
		},
		'275': {
			heights: ['30', '35', '40', '45', '50', '55', '60', '65', '70'],
			rims: {
				'30': ['R19', 'R20', 'R21', 'R24'],
				'35': ['R15', 'R17', 'R18', 'R19', 'R20', 'R21', 'R22', 'R23'],
				'40': ['R17', 'R18', 'R19', 'R20', 'R21', 'R22'],
				'45': ['R18', 'R19', 'R20', 'R21', 'R22'],
				'50': ['R19', 'R20', 'R21', 'R22', 'R24'],
				'55': ['R19', 'R20'],
				'60': ['R15', 'R20'],
				'65': ['R17', 'R18', 'R20'],
				'70': ['R16', 'R17', 'R18']
			}
		},
		'285': {
			heights: ['30', '35', '40', '45', '50', '55', '60', '65', '70', '75'],
			rims: {
				'30': ['R17', 'R18', 'R19', 'R20', 'R21', 'R22'],
				'35': ['R18', 'R19', 'R20', 'R21', 'R22', 'R23'],
				'40': ['R19', 'R20', 'R21', 'R22', 'R23', 'R24'],
				'45': ['R19', 'R20', 'R21', 'R22', 'R23'],
				'50': ['R20'],
				'55': ['R20', 'R22'],
				'60': ['R18', 'R20'],
				'65': ['R17', 'R18'],
				'70': ['R17'],
				'75': ['R16', 'R17']
			}
		},
		'295': {
			heights: ['25', '30', '35', '40', '45', '50', '55', '60', '65', '70'],
			rims: {
				'25': ['R21', 'R22'],
				'30': ['R18', 'R19', 'R20', 'R21', 'R22', 'R24', 'R26'],
				'35': ['R18', 'R19', 'R20', 'R21', 'R22', 'R23', 'R24'],
				'40': ['R19', 'R20', 'R21', 'R22'],
				'45': ['R19', 'R20'],
				'50': ['R15'],
				'55': ['R20'],
				'60': ['R20'],
				'65': ['R15'],
				'70': ['R17']
			}
		},
		'305': {
			heights: ['25', '30', '35', '40', '45', '50', '55', '70'],
			rims: {
				'25': ['R22'],
				'30': ['R19', 'R20', 'R21', 'R23', 'R26'],
				'35': ['R19', 'R20', 'R21', 'R22', 'R23', 'R24'],
				'40': ['R20', 'R22'],
				'45': ['R22'],
				'50': ['R20'],
				'55': ['R20'],
				'70': ['R16', 'R17']
			}
		},
		'315': {
			heights: ['25', '30', '35', '40', '45', '70', '75'],
			rims: {
				'25': ['R22'],
				'30': ['R18', 'R21', 'R22', 'R23'],
				'35': ['R20', 'R21', 'R22'],
				'40': ['R21'],
				'45': ['R21'],
				'70': ['R17'],
				'75': ['R16']
			}
		},
		'317': {
			heights: ['70'],
			rims: {
				'70': ['R17']
			}
		},
		'325': {
			heights: ['25', '30', '35', '40', '65'],
			rims: {
				'25': ['R20'],
				'30': ['R19', 'R20', 'R21', 'R23'],
				'35': ['R20', 'R22', 'R23'],
				'40': ['R21', 'R22', 'R23'],
				'65': ['R18']
			}
		},
		'335': {
			heights: ['25', '30'],
			rims: {
				'25': ['R20', 'R22'],
				'30': ['R20', 'R21', 'R23', 'R24']
			}
		},
		'345': {
			heights: ['25', '30'],
			rims: {
				'25': ['R21'],
				'30': ['R18', 'R20']
			}
		},
		'355': {
			heights: ['25', '30'],
			rims: {
				'25': ['R21', 'R24'],
				'30': ['R19']
			}
		}
	};

	// Flotation combinations
	var FLOTATION_COMBINATIONS = {
		'31': {
			widths: ['10', '10.5'],
			rims: {
				'10': ['R15'],
				'10.5': ['R15']
			}
		},
		'33': {
			widths: ['12.5'],
			rims: {
				'12.5': ['R15', 'R17', 'R18', 'R20', 'R22']
			}
		},
		'35': {
			widths: ['12.5'],
			rims: {
				'12.5': ['R15', 'R17', 'R18', 'R20', 'R22', 'R24']
			}
		},
		'37': {
			widths: ['12.5', '13.5'],
			rims: {
				'12.5': ['R17'],
				'13.5': ['R20', 'R22', 'R24']
			}
		},
		'38': {
			widths: ['14.5'],
			rims: {
				'14.5': ['R20']
			}
		},
		'40': {
			widths: ['14.5'],
			rims: {
				'14.5': ['R20']
			}
		}
	};

	var selection = {};
	var activeStep = 'width';
	var isFlotation = false;
	var returnFocus = null;

	function numericSort(a, b) {
		return parseInt(a, 10) - parseInt(b, 10);
	}

	function displayFor(value) {
		return value === 'none' ? 'None' : value;
	}

	function toOptions(values, kind) {
		return (values || []).map(function (value) {
			return { value: value, kind: kind, display: displayFor(value) };
		});
	}

	function stepValues() {
		if (activeStep === 'width') {
			return Object.keys(TYRE_COMBINATIONS).sort(numericSort).map(function (width) {
				return { value: width, kind: 'metric', display: width };
			}).concat(Object.keys(FLOTATION_COMBINATIONS).sort(numericSort).map(function (xValue) {
				return { value: xValue, kind: 'flotation', display: xValue + 'x' };
			}));
		}

		if (activeStep === 'height') {
			if (isFlotation) {
				var flotation = FLOTATION_COMBINATIONS[selection.width] || {};
				return toOptions(flotation.widths, 'flotation');
			}
			var metric = TYRE_COMBINATIONS[selection.width] || {};
			return toOptions(metric.heights, 'metric');
		}

		if (isFlotation) {
			var flotationRims = (FLOTATION_COMBINATIONS[selection.width] || {}).rims || {};
			return toOptions(flotationRims[selection.height], 'flotation');
		}
		var metricRims = (TYRE_COMBINATIONS[selection.width] || {}).rims || {};
		return toOptions(metricRims[selection.height], 'metric');
	}

	function currentValue() {
		if (activeStep === 'width') {
			return selection.width;
		}
		if (activeStep === 'height') {
			return selection.height;
		}
		return selection.rim;
	}

	function showSelection() {
		if (!selection.width) {
			selectionElement.textContent = 'Choose width, height and rim';
			return;
		}
		if (isFlotation) {
			selectionElement.textContent = [selection.width + 'x' + selection.height, selection.rim].filter(Boolean).join(' / ');
			return;
		}
		var parts = selection.height && selection.height !== 'none'
			? [selection.width, selection.height, selection.rim]
			: [selection.width, selection.rim];
		selectionElement.textContent = parts.filter(Boolean).join(' / ') || 'Choose width, height and rim';
	}

	function finalSelection() {
		if (isFlotation) {
			return {
				width: selection.width + 'x' + selection.height,
				height: '',
				rim: selection.rim
			};
		}
		return {
			width: selection.width,
			height: selection.height === 'none' ? '' : (selection.height || ''),
			rim: selection.rim
		};
	}

	function renderOptions() {
		optionsElement.textContent = '';
		var selected = currentValue();
		stepValues().forEach(function (option) {
			var button = document.createElement('button');
			button.className = 'custom-dubai-modal__option';
			button.type = 'button';
			button.setAttribute('role', 'option');
			button.setAttribute('data-value', option.value);
			button.setAttribute('data-kind', option.kind);
			button.setAttribute('aria-selected', selected === option.value ? 'true' : 'false');
			button.textContent = option.display;
			optionsElement.appendChild(button);
		});
	}

	function setStep(step) {
		activeStep = step;
		if (heightStepButton) {
			heightStepButton.textContent = isFlotation ? 'Section Width' : defaultHeightLabel;
		}
		Array.prototype.forEach.call(modal.querySelectorAll('[data-step]'), function (button) {
			button.setAttribute('aria-selected', button.getAttribute('data-step') === activeStep ? 'true' : 'false');
		});
		renderOptions();
	}

	function resetSelection() {
		selection = {};
		activeStep = 'width';
		isFlotation = false;
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
		resetSelection();
		modal.hidden = false;
		document.body.classList.add('custom-dubai-modal-open');
		showSelection();
		setStep('width');
		modal.querySelector('[data-modal-close]').focus();
	}

	modal.addEventListener('click', function (event) {
		var closeButton = event.target.closest('[data-modal-close]');
		if (closeButton) {
			closeModal();
			return;
		}

		var stepButton = event.target.closest('[data-step]');
		if (stepButton) {
			var requestedStep = stepButton.getAttribute('data-step');
			var lastAvailableStep = selection.width ? (selection.height ? 2 : 1) : 0;
			if (steps.indexOf(requestedStep) <= lastAvailableStep) {
				setStep(requestedStep);
			}
			return;
		}

		var optionButton = event.target.closest('[data-value]');
		if (!optionButton) {
			return;
		}

		var value = optionButton.getAttribute('data-value');
		if (activeStep === 'width') {
			isFlotation = optionButton.getAttribute('data-kind') === 'flotation';
			selection.width = value;
			delete selection.height;
			delete selection.rim;
		} else if (activeStep === 'height') {
			selection.height = value;
			delete selection.rim;
		} else {
			selection.rim = value;
		}
		showSelection();

		var nextIndex = steps.indexOf(activeStep) + 1;
		if (nextIndex < steps.length) {
			setStep(steps[nextIndex]);
			return;
		}

		document.dispatchEvent(new CustomEvent('custom-dubai:tyre-selected', { detail: finalSelection() }));
		closeModal();
	});

	document.addEventListener('custom-dubai:open-tyre-modal', openModal);
	document.addEventListener('custom-dubai:close-all-modals', function () {
		modal.hidden = true;
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && !modal.hidden) {
			closeModal();
		}
	});
	setStep('width');
})();