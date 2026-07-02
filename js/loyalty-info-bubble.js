(function () {
	function initLoyaltyInfo(container) {
		var bubble = container.querySelector('.yoswc-loyalty-info__bubble');
		var panel = container.querySelector('.yoswc-loyalty-info__panel');
		var closeButton = container.querySelector('.yoswc-loyalty-info__close');
		var sectionToggles = container.querySelectorAll('.yoswc-loyalty-info__section-toggle');

		if (!bubble || !panel || !closeButton) {
			return;
		}

		function openPanel() {
			panel.hidden = false;
			container.classList.add('is-open');
			bubble.setAttribute('aria-expanded', 'true');
		}

		function closePanel() {
			panel.hidden = true;
			container.classList.remove('is-open');
			bubble.setAttribute('aria-expanded', 'false');
		}

		function setSection(section, isOpen) {
			var toggle = section.querySelector('.yoswc-loyalty-info__section-toggle');
			var content = section.querySelector('.yoswc-loyalty-info__section-panel');

			if (!toggle || !content) {
				return;
			}

			section.classList.toggle('is-open', isOpen);
			toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			content.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
		}

		bubble.addEventListener('click', function () {
			if (panel.hidden) {
				openPanel();
			} else {
				closePanel();
			}
		});

		closeButton.addEventListener('click', function () {
			closePanel();
			bubble.focus();
		});

		document.addEventListener('click', function (event) {
			if (panel.hidden || container.contains(event.target)) {
				return;
			}

			closePanel();
		});

		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key && !panel.hidden) {
				closePanel();
				bubble.focus();
			}
		});

		sectionToggles.forEach(function (toggle) {
			toggle.addEventListener('click', function () {
				var section = toggle.closest('.yoswc-loyalty-info__section');

				if (!section) {
					return;
				}

				setSection(section, !section.classList.contains('is-open'));
			});
		});
	}

	function init() {
		var containers = document.querySelectorAll('[data-yoswc-loyalty-info]');
		containers.forEach(initLoyaltyInfo);
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
