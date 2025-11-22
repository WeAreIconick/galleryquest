/**
 * Frontend JavaScript for Gallery Quest block.
 *
 * @package
 */

import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.css';

/**
 * Initialize gallery functionality.
 */
document.addEventListener('DOMContentLoaded', () => {
	const galleryBlocks = document.querySelectorAll('.gallery-quest-block');

	galleryBlocks.forEach((block) => {
		const { galleryId } = block.dataset;
		const blockId = block.id;
		const showFilters = block.dataset.showFilters === 'true';
		const filterLogic = block.dataset.filterLogic || 'OR';

		if (!galleryId || galleryId === '0') {
			return;
		}

		// Initialize GLightbox.
		// Use the block ID to scope the selector to this specific block.
		// This prevents issues with multiple galleries on the same page.
		const lightbox = GLightbox({
			selector: `#${blockId} .gallery-quest-item-link`,
			touchNavigation: true,
			loop: true,
			zoomable: true,
			draggable: true,
			closeOnOutsideClick: true,
			keyboardNavigation: true,
		});

		// Initialize filtering if enabled.
		if (showFilters) {
			initFiltering(block, galleryId, filterLogic);
		}
	});
});

/**
 * Initialize filtering functionality.
 *
 * @param {HTMLElement} block       Gallery block element.
 * @param {string}      galleryId   Gallery ID.
 * @param {string}      filterLogic Filter logic (AND/OR).
 */
function initFiltering(block, galleryId, filterLogic) {
	const filterSelects = block.querySelectorAll('.gallery-quest-filter-select');
	const clearButton = block.querySelector('.gallery-quest-clear-filters');
	const resultsCount = block.querySelector('.gallery-quest-results-count');
	const galleryGrid = block.querySelector('.gallery-quest-grid');

	if (!filterSelects.length || !galleryGrid) {
		return;
	}

	let activeFilters = {
		character: [],
		artist: [],
		rarity: [],
	};

	/**
	 * Update active filters from UI state.
	 */
	function updateActiveFilters() {
		activeFilters = {
			character: [],
			artist: [],
			rarity: [],
		};

		filterSelects.forEach((select) => {
			if (select.value) {
				const taxonomy = select.dataset.taxonomy.replace('gallery_', '');
				const termSlug = select.value;
				if (activeFilters[taxonomy]) {
					activeFilters[taxonomy].push(termSlug);
				}
			}
		});
	}

	/**
	 * Filter gallery items.
	 */
	function filterItems() {
		updateActiveFilters();

		const items = galleryGrid.querySelectorAll('.gallery-quest-item');
		let visibleCount = 0;

		items.forEach((item) => {
			let shouldShow = true;

			if (filterLogic === 'AND') {
				// All active filters must match.
				shouldShow = Object.keys(activeFilters).every((taxonomy) => {
					if (activeFilters[taxonomy].length === 0) {
						return true; // No filter for this taxonomy.
					}
					const itemTerms = (item.dataset[taxonomy] || '').split(',').filter(Boolean);
					return activeFilters[taxonomy].some((term) => itemTerms.includes(term));
				});
			} else {
				// OR logic: at least one filter must match.
				const hasActiveFilters = Object.values(activeFilters).some((arr) => arr.length > 0);
				if (hasActiveFilters) {
					shouldShow = Object.keys(activeFilters).some((taxonomy) => {
						if (activeFilters[taxonomy].length === 0) {
							return false;
						}
						const itemTerms = (item.dataset[taxonomy] || '').split(',').filter(Boolean);
						return activeFilters[taxonomy].some((term) => itemTerms.includes(term));
					});
				}
			}

			if (shouldShow) {
				item.style.display = '';
				visibleCount++;
			} else {
				item.style.display = 'none';
			}
		});

		// Update results count.
		if (resultsCount) {
			resultsCount.textContent = `Showing ${ visibleCount } images`;
		}
	}

	// Add change handlers to filter selects.
	filterSelects.forEach((select) => {
		select.addEventListener('change', filterItems);
	});

	// Clear all filters.
	if (clearButton) {
		clearButton.addEventListener('click', () => {
			filterSelects.forEach((select) => {
				select.value = '';
			});
			filterItems();
		});
	}
}
