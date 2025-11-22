/**
 * Jest setup file for browser environment simulation
 *
 * @package
 */

// Polyfill TextEncoder/TextDecoder for Node.js
const { TextEncoder, TextDecoder } = require('util');
global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

// Mock DOM environment
const { JSDOM } = require('jsdom');

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
	url: 'http://localhost',
	pretendToBeVisual: true,
	resources: 'usable',
});

global.window = dom.window;
global.document = dom.window.document;
global.navigator = dom.window.navigator;

// Mock WordPress globals
global.wp = {
	media: {
		view: {
			Modal: {
				prototype: {
					on: jest.fn(),
				},
			},
		},
	},
};

// Mock fetch
global.fetch = jest.fn(() =>
	Promise.resolve({
		json: () => Promise.resolve([]),
		ok: true,
		status: 200,
	})
);

// Mock console methods to reduce noise in tests
global.console = {
	...console,
	log: jest.fn(),
	debug: jest.fn(),
	info: jest.fn(),
	warn: jest.fn(),
	error: jest.fn(),
};
