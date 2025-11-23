/**
 * Enhanced select dropdowns with "Add New" functionality for attachment taxonomy fields
 *
 * @package GalleryQuest
 */

(function () {
	'use strict';

	// Early exit if WordPress globals aren't available
	if (typeof window.galleryQuestAttachmentFields === 'undefined') {
		return;
	}

	// Prevent multiple script loads
	if (window.galleryQuestAttachmentFieldsInitialized) {
		return;
	}
	window.galleryQuestAttachmentFieldsInitialized = true;

	/**
	 * Add a new term to a taxonomy
	 */
	function addNewTerm(taxonomy, termName, postId, selectElement) {
		if (!termName || !termName.trim()) {
			return;
		}

		const termNameTrimmed = termName.trim();

		// Check if term already exists in the select
		const existingOptions = Array.from(selectElement.options);
		const termExists = existingOptions.some(option => option.textContent.trim() === termNameTrimmed);

		if (termExists) {
			alert('This term already exists!');
			return;
		}

		// Create term via REST API
		const apiUrl = `${galleryQuestAttachmentFields.restUrl}wp/v2/${taxonomy}`;
		
		fetch(apiUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': galleryQuestAttachmentFields.nonce,
			},
			body: JSON.stringify({
				name: termNameTrimmed,
			}),
		})
			.then((response) => {
				if (!response.ok) {
					throw new Error(`HTTP error! status: ${response.status}`);
				}
				return response.json();
			})
			.then((newTerm) => {
				// Add the new term to the select dropdown
				const option = document.createElement('option');
				option.value = newTerm.id;
				option.textContent = newTerm.name;
				option.selected = true; // Auto-select the newly created term
				selectElement.appendChild(option);

				// Clear the input field
				const input = document.querySelector(`.gallery-quest-new-term-input[data-taxonomy="${taxonomy}"][data-post-id="${postId}"]`);
				if (input) {
					input.value = '';
				}

				// Trigger change event so WordPress knows the select changed
				selectElement.dispatchEvent(new Event('change', { bubbles: true }));
			})
			.catch((error) => {
				console.error('Gallery Quest: Failed to create term', error);
				alert('Failed to create term. Please try again.');
			});
	}

	/**
	 * Enhance select dropdowns with better styling and "Add New" functionality
	 */
	function enhanceSelects() {
		const selects = document.querySelectorAll('.gallery-quest-taxonomy-select');

		selects.forEach((select) => {
			// Skip if already enhanced
			if (select.dataset.gqEnhanced === 'true') {
				return;
			}
			select.dataset.gqEnhanced = 'true';

			// Add some basic styling improvements
			select.style.padding = '6px 8px';
			select.style.border = '1px solid #8c8f94';
			select.style.borderRadius = '3px';
			select.style.fontSize = '14px';
			select.style.lineHeight = '1.5';

			// Add focus styles
			select.addEventListener('focus', function() {
				this.style.borderColor = '#2271b1';
				this.style.boxShadow = '0 0 0 1px #2271b1';
				this.style.outline = 'none';
			});

			select.addEventListener('blur', function() {
				this.style.borderColor = '#8c8f94';
				this.style.boxShadow = 'none';
			});
		});
	}

	/**
	 * Initialize when DOM is ready
	 */
	function initialize() {
		// Only initialize if we're on an attachment edit page
		const isAttachmentPage =
			document.querySelector('.attachment-details') ||
			document.querySelector('.compat-attachment-fields') ||
			window.location.href.includes('post.php?post=');

		if (!isAttachmentPage) {
			return;
		}

		// Enhance selects immediately
		enhanceSelects();

		// Retry a few times in case selects load dynamically
		let retryCount = 0;
		const maxRetries = 5;
		const retryInterval = setInterval(() => {
			retryCount++;
			const selects = document.querySelectorAll('.gallery-quest-taxonomy-select');
			
			if (selects.length > 0) {
				enhanceSelects();
			}

			if (retryCount >= maxRetries) {
				clearInterval(retryInterval);
			}
		}, 500);
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialize, { once: true });
	} else {
		setTimeout(initialize, 500);
	}
})();
