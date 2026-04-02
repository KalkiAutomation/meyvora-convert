(function (blocks, element, blockEditor, components, serverSideRender) {
	var registerBlockType = blocks.registerBlockType;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var Fragment = element.Fragment;
	var createElement = element.createElement;

	registerBlockType('meyvora-convert/campaign', {
		edit: function (props) {
			var blockProps = useBlockProps();
			var campaignId = props.attributes.campaignId;
			var setAttributes = props.setAttributes;
			var campaigns = (window.meyvcCampaignBlock && window.meyvcCampaignBlock.campaigns) || [];
			var options = [
				{ value: 0, label: '— Select campaign —' }
			].concat(
				campaigns.map(function (c) {
					return { value: c.id, label: (c.name || 'Campaign #' + c.id) + (c.status && c.status !== 'active' ? ' (' + c.status + ')' : '') };
				})
			);

			return createElement(
				Fragment,
				null,
				createElement(
					InspectorControls,
					{ key: 'inspector' },
					createElement(
						PanelBody,
						{ title: 'Campaign', initialOpen: true },
						createElement(SelectControl, {
							label: 'Campaign',
							value: campaignId,
							options: options,
							onChange: function (value) {
								setAttributes({ campaignId: value ? parseInt(value, 10) : 0 });
							}
						})
					)
				),
				createElement(
					'div',
					blockProps,
					campaignId
						? createElement(
								serverSideRender,
								{
									block: 'meyvora-convert/campaign',
									attributes: { campaignId: campaignId }
								}
						  )
						: createElement(
								'p',
								{ className: 'meyvc-campaign-placeholder', style: { padding: '1em', color: '#666', fontStyle: 'italic' } },
								'Select a campaign in the sidebar.'
						  )
				)
			);
		},

		save: function () {
			return null;
		}
	});
})(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender
);
