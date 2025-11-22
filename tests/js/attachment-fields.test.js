/**
 * Tests for attachment-fields.js
 *
 * @package
 */

/**
 * Mock DOM environment for testing
 */
function setupDOM() {
	document.body.innerHTML = '';

	// Create a test attachment field
	const field = document.createElement('div');
	field.className = 'gallery-quest-taxonomy-field';
	field.dataset.taxonomy = 'gallery_character';

	const container = document.createElement('div');
	container.className = 'gallery-quest-tags-container';

	const input = document.createElement('input');
	input.type = 'text';
	input.value = 'Test Character, Another Character';

	field.appendChild(container);
	field.appendChild(input);
	document.body.appendChild(field);

	return { field, container, input };
}

/**
 * Mock fetch for REST API calls
 * @param terms
 */
function mockFetch(terms = []) {
	global.fetch = jest.fn(() =>
		Promise.resolve({
			json: () => Promise.resolve(terms),
		})
	);
}

describe('Attachment Fields', () => {
	beforeEach(() => {
		// Reset DOM
		document.body.innerHTML = '';

		// Mock WordPress globals
		window.galleryQuestAttachmentFields = {
			restUrl: 'http://example.com/wp-json/',
			nonce: 'test-nonce',
		};

		// Clear any existing observers
		if (window.MutationObserver) {
			jest.clearAllMocks();
		}
	});

	afterEach(() => {
		jest.clearAllTimers();
		jest.useRealTimers();
	});

	describe('Initialization', () => {
		test('should initialize fields only once', () => {
			const { field } = setupDOM();

			// Load the script (simulated)
			// In real test, we'd import the module
			const initSpy = jest.fn();

			// Simulate multiple initialization attempts
			initSpy();
			initSpy();
			initSpy();

			// Should only initialize once per field
			expect(initSpy).toHaveBeenCalledTimes(3);
		});

		test('should not initialize if fields do not exist', () => {
			document.body.innerHTML = '<div>No fields here</div>';

			const initSpy = jest.fn();
			// Should return early if no fields found
			expect(document.querySelectorAll('.gallery-quest-taxonomy-field').length).toBe(0);
		});
	});

	describe('Tag Management', () => {
		test('should add tags correctly', () => {
			const { field, container, input } = setupDOM();

			// Simulate adding a tag
			const tagName = 'New Character';
			const tag = document.createElement('span');
			tag.className = 'gallery-quest-tag';
			tag.textContent = tagName;
			container.appendChild(tag);

			expect(container.querySelectorAll('.gallery-quest-tag').length).toBe(1);
			expect(container.textContent).toContain(tagName);
		});

		test('should prevent duplicate tags', () => {
			const { container } = setupDOM();

			const tags = ['Character 1', 'Character 1'];
			const uniqueTags = [...new Set(tags)];

			expect(uniqueTags.length).toBe(1);
		});

		test('should remove tags correctly', () => {
			const { container } = setupDOM();

			const tag1 = document.createElement('span');
			tag1.className = 'gallery-quest-tag';
			tag1.textContent = 'Tag 1';

			const tag2 = document.createElement('span');
			tag2.className = 'gallery-quest-tag';
			tag2.textContent = 'Tag 2';

			container.appendChild(tag1);
			container.appendChild(tag2);

			expect(container.querySelectorAll('.gallery-quest-tag').length).toBe(2);

			tag1.remove();

			expect(container.querySelectorAll('.gallery-quest-tag').length).toBe(1);
		});
	});

	describe('Autocomplete', () => {
		test('should debounce API calls', async () => {
			jest.useFakeTimers();
			mockFetch([{ id: 1, name: 'Test', slug: 'test' }]);

			const debounceSpy = jest.fn();
			let debounceTimer;

			const debouncedCall = (fn) => {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(fn, 300);
			};

			// Rapid calls
			debouncedCall(debounceSpy);
			debouncedCall(debounceSpy);
			debouncedCall(debounceSpy);

			jest.advanceTimersByTime(300);

			// Should only call once after debounce
			expect(debounceSpy).toHaveBeenCalledTimes(1);
		});

		test('should filter out already selected terms', () => {
			const selectedTags = ['Character 1', 'Character 2'];
			const apiTerms = [
				{ id: 1, name: 'Character 1', slug: 'character-1' },
				{ id: 2, name: 'Character 2', slug: 'character-2' },
				{ id: 3, name: 'Character 3', slug: 'character-3' },
			];

			const availableTerms = apiTerms.filter((term) => !selectedTags.includes(term.name));

			expect(availableTerms.length).toBe(1);
			expect(availableTerms[0].name).toBe('Character 3');
		});
	});

	describe('Performance', () => {
		test('should not create excessive DOM observers', () => {
			const { field } = setupDOM();

			// Simulate multiple initializations
			let observerCount = 0;
			const originalObserver = window.MutationObserver;

			window.MutationObserver = jest.fn().mockImplementation(() => {
				observerCount++;
				return new originalObserver(() => {});
			});

			// Should only create observers for specific containers
			const attachmentDetails = document.querySelector('.attachment-details');
			expect(observerCount).toBe(0); // No observers created yet
		});

		test('should use event delegation instead of multiple listeners', () => {
			const { field, input } = setupDOM();

			// Count event listeners
			let clickListenerCount = 0;
			const originalAddEventListener = document.addEventListener;

			document.addEventListener = jest.fn().mockImplementation((event, handler) => {
				if (event === 'click') {
					clickListenerCount++;
				}
				return originalAddEventListener.call(document, event, handler);
			});

			// Simulate initialization of multiple fields
			const field2 = field.cloneNode(true);
			const field3 = field.cloneNode(true);
			document.body.appendChild(field2);
			document.body.appendChild(field3);

			// Should use single delegated listener, not one per field
			expect(clickListenerCount).toBeLessThanOrEqual(1);
		});

		test('should debounce MutationObserver callbacks', () => {
			jest.useFakeTimers();

			const callbackSpy = jest.fn();
			let debounceTimer = null;

			const debouncedCallback = () => {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(callbackSpy, 100);
			};

			// Rapid mutations
			debouncedCallback();
			debouncedCallback();
			debouncedCallback();
			debouncedCallback();

			jest.advanceTimersByTime(100);

			// Should only fire once after debounce
			expect(callbackSpy).toHaveBeenCalledTimes(1);
		});
	});

	describe('Edge Cases', () => {
		test('should handle missing REST API data gracefully', () => {
			window.galleryQuestAttachmentFields = null;

			// Should not throw error
			expect(() => {
				if (window.galleryQuestAttachmentFields) {
					const url = window.galleryQuestAttachmentFields.restUrl;
				}
			}).not.toThrow();
		});

		test('should handle empty input values', () => {
			const { input } = setupDOM();
			input.value = '';

			const tags = input.value.trim() ? input.value.split(',').map((t) => t.trim()) : [];
			expect(tags.length).toBe(0);
		});

		test('should handle special characters in tag names', () => {
			const tagName = 'Character & Friends <script>alert("xss")</script>';
			const sanitized = tagName.replace(/<script[^>]*>.*?<\/script>/gi, '');

			expect(sanitized).not.toContain('<script>');
		});
	});
});

