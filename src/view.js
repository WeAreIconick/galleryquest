/**
 * Frontend JavaScript for Gallery Quest block.
 *
 * @package
 */

import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

/**
 * Initialize gallery functionality.
 */
function initGalleryQuest() {
	const galleryBlocks = document.querySelectorAll('.gallery-quest-block');

	if (!galleryBlocks.length) {
		return;
	}

	galleryBlocks.forEach((block) => {
		// Check if already initialized
		if (block.dataset.gqInitialized === 'true') {
			return;
		}
		block.dataset.gqInitialized = 'true';

		const { galleryId } = block.dataset;
		const blockId = block.id;
		const showFilters = block.dataset.showFilters === 'true';
		const filterLogic = block.dataset.filterLogic || 'OR';

		if (!galleryId || galleryId === '0') {
			return;
		}

		// Initialize PhotoSwipe
		const lightbox = new PhotoSwipeLightbox({
			gallery: `#${blockId}`,
			children: '.gallery-quest-item-link',
			pswpModule: () => import('photoswipe'),
			arrowPrev: false,
			arrowNext: false,
			paddingFn: (viewportSize) => {
				return {
					top: 30,
					bottom: 30,
					left: 70,
					right: 70
				};
			},
		});
		
		lightbox.init();

		// Initialize filtering if enabled.
		if (showFilters) {
			initFiltering(block, galleryId, filterLogic);
		}
	});
}

// Initialize on DOM content loaded
document.addEventListener('DOMContentLoaded', initGalleryQuest);

// Re-initialize on cache hits/AJAX loads (e.g., infinite scroll, PJAX)
window.addEventListener('load', initGalleryQuest);

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
