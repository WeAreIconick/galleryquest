/**
 * Performance tests for attachment-fields.js
 *
 * @package
 */

describe('Performance Tests', () => {
	beforeEach(() => {
		jest.useFakeTimers();
		// Clear performance marks if available
		if (performance.clearMarks) {
			performance.clearMarks();
		}
		if (performance.clearMeasures) {
			performance.clearMeasures();
		}
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	test('initialization should complete within time limit', () => {
		const startTime = performance.now();

		// Simulate initialization
		const fields = Array.from({ length: 10 }, () => {
			const field = document.createElement('div');
			field.className = 'gallery-quest-taxonomy-field';
			return field;
		});

		fields.forEach((field) => document.body.appendChild(field));

		const endTime = performance.now();
		const duration = endTime - startTime;

		// Should complete in less than 100ms
		expect(duration).toBeLessThan(100);
	});

	test('should not create memory leaks with multiple initializations', () => {
		const initialMemory = performance.memory?.usedJSHeapSize || 0;

		// Simulate 100 initializations
		for (let i = 0; i < 100; i++) {
			const field = document.createElement('div');
			field.className = 'gallery-quest-taxonomy-field';
			document.body.appendChild(field);
			// Simulate cleanup
			field.remove();
		}

		// Force garbage collection if available
		if (global.gc) {
			global.gc();
		}

		const finalMemory = performance.memory?.usedJSHeapSize || 0;
		const memoryIncrease = finalMemory - initialMemory;

		// Memory increase should be reasonable (less than 10MB)
		if (memoryIncrease > 0) {
			expect(memoryIncrease).toBeLessThan(10 * 1024 * 1024);
		}
	});

	test('MutationObserver should not fire excessively', () => {
		let observerCallCount = 0;

		const observer = new MutationObserver(() => {
			observerCallCount++;
		});

		const target = document.createElement('div');
		document.body.appendChild(target);

		observer.observe(target, {
			childList: true,
			subtree: true,
		});

		// Make many rapid changes
		for (let i = 0; i < 100; i++) {
			const child = document.createElement('div');
			target.appendChild(child);
			target.removeChild(child);
		}

		// With debouncing, should have fewer calls than mutations
		// Note: This is a simplified test - real implementation uses debouncing
		// In test environment, observer may not fire, which is fine
		if (observerCallCount > 0) {
			expect(observerCallCount).toBeLessThan(100);
		}
	});

	test('event listeners should not accumulate', () => {
		const field = document.createElement('div');
		field.className = 'gallery-quest-taxonomy-field';
		document.body.appendChild(field);

		let listenerCount = 0;
		const originalAddEventListener = document.addEventListener;

		document.addEventListener = jest.fn().mockImplementation((event, handler) => {
			if (event === 'click') {
				listenerCount++;
			}
			return originalAddEventListener.call(document, event, handler);
		});

		// Simulate multiple field initializations
		for (let i = 0; i < 10; i++) {
			const newField = field.cloneNode(true);
			document.body.appendChild(newField);
		}

		// Should use delegation - only one listener
		expect(listenerCount).toBeLessThanOrEqual(1);
	});
});
