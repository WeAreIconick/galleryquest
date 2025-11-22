/**
 * Gallery Meta Panel - Block Editor Component
 *
 * @package
 */

import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { subscribe } from '@wordpress/data';

/**
 * Gallery Images Panel Component
 */
function GalleryImagesPanel() {
	const { editPost } = useDispatch('core/editor');
	const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
	const meta = useSelect((select) => {
		return select('core/editor').getEditedPostAttribute('meta');
	}, []);
	
	// Also try to get from the post object directly (for register_rest_field)
	const post = useSelect((select) => {
		return select('core/editor').getCurrentPost();
	}, []);

	const [images, setImages] = useState([]);
	const [loading, setLoading] = useState(false);

	const attachmentIds = useMemo(() => {
		// Try meta first, then top-level field (for register_rest_field)
		return meta?._gallery_quest_images || post?._gallery_quest_images || [];
	}, [meta?._gallery_quest_images, post?._gallery_quest_images]);

	// Load image data when attachment IDs change.
	useEffect(() => {
		if (attachmentIds.length === 0) {
			setImages([]);
			setLoading(false);
			return;
		}

		let isMounted = true;
		setLoading(true);

		Promise.all(
			attachmentIds.map((id) =>
				apiFetch({
					path: `/wp/v2/media/${id}`,
				}).catch(() => null)
			)
		).then((results) => {
			if (isMounted) {
				setImages(results.filter(Boolean));
				setLoading(false);
			}
		});

		return () => {
			isMounted = false;
		};
	}, [attachmentIds]);

	const onSelectImages = useCallback(
		(selectedImages) => {
			const ids = selectedImages.map((img) => img.id);
			// Ensure we're saving an array of integers
			const sanitizedIds = ids.filter((id) => Number.isInteger(id) && id > 0);
			
			console.log('Gallery Quest: Saving images to editor state', sanitizedIds);
			console.log('Gallery Quest: Current meta before update', meta);
			
			const updatedMeta = {
				...meta,
				_gallery_quest_images: sanitizedIds,
			};
			
			console.log('Gallery Quest: Updated meta to save', updatedMeta);
			
			// Update editor state
			editPost({
				meta: updatedMeta,
			});
			
			// Save via our custom endpoint (more reliable)
			if (postId) {
				console.log('Gallery Quest: Saving meta via custom endpoint for post ID:', postId);
				apiFetch({
					path: `/gallery-quest/v1/save-images/${postId}`,
					method: 'POST',
					data: {
						images: sanitizedIds,
					},
				}).then((response) => {
					console.log('Gallery Quest: Custom endpoint response:', response);
					if (response.success && response.images) {
						// Update editor state with saved images
						editPost({
							meta: {
								...meta,
								_gallery_quest_images: response.images,
							},
						});
						console.log('Gallery Quest: Updated editor state with saved images:', response.images);
					}
				}).catch((error) => {
					console.error('Gallery Quest: Error saving via custom endpoint:', error);
					
					// Fallback: try standard REST API
					console.log('Gallery Quest: Falling back to standard REST API...');
					apiFetch({
						path: `/wp/v2/gallery-quest/${postId}`,
						method: 'POST',
						data: {
							meta: {
								_gallery_quest_images: sanitizedIds,
							},
							gallery_quest_images: sanitizedIds,
							_gallery_quest_images: sanitizedIds,
						},
					}).then((response) => {
						console.log('Gallery Quest: Fallback REST API response:', response);
					}).catch((fallbackError) => {
						console.error('Gallery Quest: Fallback also failed:', fallbackError);
					});
				});
			}
		},
		[editPost, meta]
	);

	const removeImage = useCallback(
		(imageId) => {
			const newIds = attachmentIds.filter((id) => id !== imageId);
			// Ensure we're saving an array of integers
			const sanitizedIds = newIds.filter((id) => Number.isInteger(id) && id > 0);
			
			editPost({
				meta: {
					...meta,
					_gallery_quest_images: sanitizedIds.length > 0 ? sanitizedIds : [],
				},
			});
		},
		[attachmentIds, editPost, meta]
	);

	return (
		<PluginDocumentSettingPanel
			name="gallery-quest-images"
			title={__('Gallery Images', 'gallery-quest')}
		>
			<MediaUploadCheck>
				<MediaUpload
					onSelect={onSelectImages}
					allowedTypes={['image']}
					multiple
					value={attachmentIds}
					render={({ open }) => (
						<Button onClick={open} variant="primary">
							{attachmentIds.length > 0
								? __('Change Images', 'gallery-quest')
								: __('Select Images', 'gallery-quest')}
						</Button>
					)}
				/>
			</MediaUploadCheck>

			{loading && <Spinner />}

			{!loading && images.length > 0 && (
				<div style={{ marginTop: '16px' }}>
					<div
						style={{
							display: 'grid',
							gridTemplateColumns: 'repeat(auto-fill, minmax(100px, 1fr))',
							gap: '8px',
							marginTop: '16px',
						}}
					>
						{images.map((image) => (
							<div
								key={image.id}
								style={{
									position: 'relative',
									aspectRatio: '1/1',
									borderRadius: '4px',
									overflow: 'hidden',
								}}
							>
								<img
									src={image.source_url}
									alt={image.alt_text || ''}
									style={{
										width: '100%',
										height: '100%',
										objectFit: 'cover',
									}}
								/>
								<Button
									onClick={() => removeImage(image.id)}
									isDestructive
									style={{
										position: 'absolute',
										top: '4px',
										right: '4px',
										minWidth: '24px',
										width: '24px',
										height: '24px',
										padding: 0,
									}}
									aria-label={__('Remove image', 'gallery-quest')}
								>
									×
								</Button>
							</div>
						))}
					</div>
					<p style={{ marginTop: '16px', fontSize: '13px', color: '#757575' }}>
						{__(`${images.length} image${images.length !== 1 ? 's' : ''} selected`, 'gallery-quest')}
					</p>
				</div>
			)}

			{!loading && attachmentIds.length === 0 && (
				<p style={{ marginTop: '16px', fontSize: '13px', color: '#757575' }}>
					{__(
						'No images selected. Click "Select Images" to add images to this gallery.',
						'gallery-quest'
					)}
				</p>
			)}
		</PluginDocumentSettingPanel>
	);
}

/**
 * Register the plugin panel
 */
import { registerPlugin } from '@wordpress/plugins';

// Subscribe to editor save events to manually save meta
if (typeof window !== 'undefined' && window.wp?.data) {
	const { select } = window.wp.data;
	
	// Track previous save state
	let wasSaving = false;
	let saveTimeout = null;
	
	// Subscribe to editor state changes
	const unsubscribe = subscribe(() => {
		const store = select('core/editor');
		if (!store) return;
		
		const currentPostId = store.getCurrentPostId();
		const currentMeta = store.getEditedPostAttribute('meta');
		const isSaving = store.isSavingPost();
		const isAutosaving = store.isAutosavingPost();
		
		// Clear any pending timeout
		if (saveTimeout) {
			clearTimeout(saveTimeout);
			saveTimeout = null;
		}
		
		// Detect when save completes (was saving, now not saving)
		if (wasSaving && !isSaving && !isAutosaving && currentPostId) {
			const images = currentMeta?._gallery_quest_images || [];
			
			// Wait a bit for WordPress to finish processing, then save meta
			saveTimeout = setTimeout(() => {
				if (images.length > 0) {
					console.log('Gallery Quest: Save completed, manually saving meta...', images);
					
					// Use window.wp.apiFetch to ensure interception works
					const saveMeta = window.wp?.apiFetch || apiFetch;
					saveMeta({
						path: `/wp/v2/gallery-quest/${currentPostId}`,
						method: 'POST',
						data: {
							meta: {
								_gallery_quest_images: images,
							},
						},
					}).then((response) => {
						console.log('Gallery Quest: Manually saved meta successfully:', response);
						console.log('Gallery Quest: Response meta:', response.meta);
					}).catch((error) => {
						console.error('Gallery Quest: Error manually saving meta:', error);
					});
				} else {
					console.log('Gallery Quest: Save completed but no images to save');
				}
			}, 500); // Wait 500ms after save completes
		}
		
		// Update tracking
		wasSaving = isSaving;
	});
	
	console.log('Gallery Quest: Editor save subscription setup complete');
}

// Hook into the REST API request to log what's being sent
if (typeof window !== 'undefined') {
	// Intercept ALL apiFetch calls
	const originalApiFetch = window.wp?.apiFetch || apiFetch;
	
	const interceptApiFetch = function(options) {
		// Log ALL requests to gallery-quest
		if (options?.path?.includes('/gallery-quest')) {
			console.log('Gallery Quest: API Request:', {
				path: options.path,
				method: options.method || 'GET',
				body: options.body,
				data: options.data,
			});
		}
		
		// Log save requests specifically
		if (options?.path?.includes('/wp/v2/gallery-quest/') && 
			(options?.method === 'POST' || options?.method === 'PUT' || options?.method === 'PATCH')) {
			console.log('Gallery Quest: SAVE Request detected:', {
				path: options.path,
				method: options.method,
				body: JSON.stringify(options.body || {}),
				data: JSON.stringify(options.data || {}),
			});
			
			// Check if meta is in the request
			const requestData = options.body || options.data || {};
			if (requestData.meta) {
				console.log('Gallery Quest: Meta in save request:', requestData.meta);
			} else {
				console.warn('Gallery Quest: NO META in save request!', requestData);
			}
		}
		
		return originalApiFetch.apply(this, arguments);
	};
	
	// Replace window.wp.apiFetch if it exists
	if (window.wp?.apiFetch) {
		window.wp.apiFetch = interceptApiFetch;
	}
	
	console.log('Gallery Quest: apiFetch interception setup complete');
}

registerPlugin('gallery-quest-images-panel', {
	render: GalleryImagesPanel,
	icon: null,
});
