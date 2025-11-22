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
import { registerPlugin } from '@wordpress/plugins';

/**
 * Gallery Images Panel Component
 */
function GalleryImagesPanel() {
	const { editPost } = useDispatch('core/editor');
	const meta = useSelect((select) => {
		return select('core/editor').getEditedPostAttribute('meta');
	}, []);

	const [images, setImages] = useState([]);
	const [loading, setLoading] = useState(false);

	const attachmentIds = useMemo(() => {
		return meta?._gallery_quest_images || [];
	}, [meta?._gallery_quest_images]);

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
			
			// Update editor state - WordPress core handles the REST API save
			editPost({
				meta: {
					...meta,
					_gallery_quest_images: sanitizedIds,
				},
			});
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

registerPlugin('gallery-quest-images-panel', {
	render: GalleryImagesPanel,
	icon: null,
});
