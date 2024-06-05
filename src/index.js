/**
 * External dependencies
 */
import { addAction, addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import * as Woo from '@woocommerce/components';
import { Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './index.scss';

const MyExamplePage = () => {
	return (
		<Fragment>
			<Woo.Section component="article">
				<Woo.SectionHeader title={__('Welcome to Mottasl!!', 'Mottasl')} />
				Mottasl is a business communication app that integrates and
				streamlines communication with stores and their customers. It
				provides tools for customer support, marketing, sales and more.
			</Woo.Section>
		</Fragment>
	);
};
addAction();
addFilter('woocommerce_admin_pages_list', 'mottasl', (pages) => {
	pages.push({
		container: MyExamplePage,
		path: '/mottasl',
		breadcrumbs: [__('Mottasl', 'mottasl')],
		navArgs: {
			id: 'mottasl',
		},
	});

	return pages;
});
