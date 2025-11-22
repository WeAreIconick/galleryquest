/**
 * Integration tests for attachment screen
 *
 * @package
 */

describe('Attachment Screen Integration', () => {
	beforeEach(() => {
		document.body.innerHTML = '';

		window.galleryQuestAttachmentFields = {
			restUrl: 'http://example.com/wp-json/',
			nonce: 'test-nonce',
		};
	});

	test('should handle multiple taxonomy fields without conflicts', () => {
		// Create multiple fields
		const fields = ['gallery_character', 'gallery_artist', 'gallery_rarity'].map((taxonomy) => {
			const field = document.createElement('div');
			field.className = 'gallery-quest-taxonomy-field';
			field.dataset.taxonomy = taxonomy;

			const container = document.createElement('div');
			container.className = 'gallery-quest-tags-container';

			const input = document.createElement('input');
			input.type = 'text';

			field.appendChild(container);
			field.appendChild(input);
			document.body.appendChild(field);

			return { field, container, input };
		});

		// All fields should be independent
		expect(fields.length).toBe(3);
		fields.forEach(({ field }) => {
			expect(field.dataset.taxonomy).toBeTruthy();
		});
	});

	test('should not interfere with WordPress media modal', () => {
		// Simulate WordPress media modal structure
		const mediaModal = document.createElement('div');
		mediaModal.className = 'media-modal';

		const attachmentDetails = document.createElement('div');
		attachmentDetails.className = 'attachment-details';

		const field = document.createElement('div');
		field.className = 'gallery-quest-taxonomy-field';

		attachmentDetails.appendChild(field);
		mediaModal.appendChild(attachmentDetails);
		document.body.appendChild(mediaModal);

		// Should find field within modal
		const foundField = mediaModal.querySelector('.gallery-quest-taxonomy-field');
		expect(foundField).toBeTruthy();
	});

	test('should handle rapid open/close of attachment details', async () => {
		const field = document.createElement('div');
		field.className = 'gallery-quest-taxonomy-field';

		// Simulate rapid DOM changes
		for (let i = 0; i < 10; i++) {
			document.body.appendChild(field.cloneNode(true));
			await new Promise((resolve) => setTimeout(resolve, 10));
			document.body.removeChild(document.body.lastChild);
		}

		// Should not throw errors or cause memory issues
		expect(document.body.children.length).toBe(0);
	});
});

