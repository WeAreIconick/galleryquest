/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * WordPress components
 */
import {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	Placeholder,
} from '@wordpress/components';

/**
 * WordPress data hooks
 */
import { useEntityRecords } from '@wordpress/core-data';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 * @param          props.attributes
 * @param          props.setAttributes
 *
 * @param {Object} props               Block props.
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { galleryId, itemCount, columns, showFilters, filterLogic } = attributes;
	const blockProps = useBlockProps();

	// Fetch all gallery_quest posts.
	const { records: galleries, isResolving } = useEntityRecords('postType', 'gallery_quest', {
		per_page: -1,
	});

	// Get selected gallery.
	const selectedGallery = galleries?.find((gallery) => gallery.id === galleryId);

	// Build gallery options for SelectControl.
	const galleryOptions = [
		{ label: __('Select a gallery…', 'gallery-quest'), value: 0 },
		...(galleries || []).map((gallery) => ({
			label: gallery.title?.rendered || __('(No title)', 'gallery-quest'),
			value: gallery.id,
		})),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Gallery Settings', 'gallery-quest')}>
					<SelectControl
						label={__('Select Gallery', 'gallery-quest')}
						value={galleryId}
						options={galleryOptions}
						onChange={(value) => setAttributes({ galleryId: parseInt(value, 10) })}
						disabled={isResolving}
					/>

					{galleryId > 0 && (
						<>
							<RangeControl
								label={__('Items to Display', 'gallery-quest')}
								value={itemCount}
								onChange={(value) => setAttributes({ itemCount: value })}
								min={4}
								max={100}
							/>

							<RangeControl
								label={__('Columns', 'gallery-quest')}
								value={columns}
								onChange={(value) => setAttributes({ columns: value })}
								min={1}
								max={6}
							/>

							<ToggleControl
								label={__('Show Filter Controls', 'gallery-quest')}
								checked={showFilters}
								onChange={(value) => setAttributes({ showFilters: value })}
							/>

							{showFilters && (
								<SelectControl
									label={__('Filter Logic', 'gallery-quest')}
									value={filterLogic}
									options={[
										{
											label: __('Match ANY filter (OR)', 'gallery-quest'),
											value: 'OR',
										},
										{
											label: __('Match ALL filters (AND)', 'gallery-quest'),
											value: 'AND',
										},
									]}
									onChange={(value) => setAttributes({ filterLogic: value })}
								/>
							)}
						</>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{!galleryId || galleryId === 0 ? (
					<Placeholder
						icon="format-gallery"
						label={__('Gallery Quest', 'gallery-quest')}
						instructions={__('Select a gallery from the sidebar to display its images.', 'gallery-quest')}
					>
						{isResolving && <p>{__('Loading galleries…', 'gallery-quest')}</p>}
						{!isResolving && galleries?.length === 0 && (
							<p>{__('No galleries found. Create a gallery post first.', 'gallery-quest')}</p>
						)}
					</Placeholder>
				) : (
					<div className="gallery-quest-editor-preview">
						<h3>{selectedGallery?.title?.rendered || __('Gallery', 'gallery-quest')}</h3>
						<p>
							{__('Gallery preview:', 'gallery-quest')} {__('Up to', 'gallery-quest')} {itemCount}{' '}
							{__('images in', 'gallery-quest')} {columns}{' '}
							{columns === 1 ? __('column', 'gallery-quest') : __('columns', 'gallery-quest')}
							{showFilters && ` (${__('with filters', 'gallery-quest')})`}
						</p>
					</div>
				)}
			</div>
		</>
	);
}
