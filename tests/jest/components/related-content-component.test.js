/* global global describe it expect beforeEach */

const VueTestUtils = require( '@vue/test-utils' );
const RelatedContentComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/RelatedContentComponent.vue' );
const { when } = require( 'jest-when' );

describe( 'Related content component', () => {
	const donorFaqUrl = 'https://faq.example.org';
	const otherWaysUrl = 'https://otherways.example.org';
	const legacyUrl = 'https://legacy.example.org';
	const newDonationUrl = 'https://donate.example.org';
	// Mirrors the shape of DonationInterfaceWikipediaVideoSources ( see extension.json ).
	const wikipediaVideoSources = [
		{
			url: 'https://upload.wikimedia.org/wikipedia/commons/transcoded/video.webm.1080p.vp9.webm',
			type: 'video/webm; codecs="vp9, opus"'
		},
		{
			url: 'https://upload.wikimedia.org/wikipedia/commons/transcoded/video.webm.360p.webm',
			type: 'video/webm; codecs="vp8, vorbis"'
		}
	];

	beforeEach( () => {
		when( global.mw.config.get ).calledWith( 'wikipediaVideoSources' ).mockReturnValue( wikipediaVideoSources );
		when( global.mw.config.get ).calledWith( 'donorFaqUrl' ).mockReturnValue( donorFaqUrl );
		when( global.mw.config.get ).calledWith( 'otherWaysUrl' ).mockReturnValue( otherWaysUrl );
		when( global.mw.config.get ).calledWith( 'legacyUrl' ).mockReturnValue( legacyUrl );
		when( global.mw.config.get ).calledWith( 'newDonationUrl' ).mockReturnValue( newDonationUrl );
	} );

	it( 'Renders the aside content with FAQ links resolved from config', () => {
		const wrapper = VueTestUtils.mount( RelatedContentComponent );

		const aside = wrapper.find( '.dp-dashboard__aside' );
		expect( aside.exists() ).toBe( true );

		// Fun-fact widget renders its heading and body text.
		expect( aside.html() ).toContain( 'donorportal-aside-did-you-know' );
		expect( aside.html() ).toContain( 'donorportal-aside-fun-fact' );

		// Each button in the stack links to its configured URL, in order.
		const buttonLinks = wrapper.findAll( '.dp-button-stack a' );
		expect( buttonLinks.map( ( link ) => link.attributes( 'href' ) ) ).toEqual( [
			donorFaqUrl,
			otherWaysUrl,
			legacyUrl,
			newDonationUrl
		] );
	} );

	it( 'Renders each configured video source when the how-wikipedia-works popup is opened', async () => {
		const wrapper = VueTestUtils.mount( RelatedContentComponent );

		// The video modal ( and its <source> list ) only renders once the popup opens,
		// which requires clicking the thumbnail link.
		const videoTrigger = wrapper.find( '.dp-card__player .link' );
		await videoTrigger.trigger( 'click' );

		const sources = wrapper.findAll( '#wikipediaVideo source' );
		expect( sources.length ).toBe( wikipediaVideoSources.length );
		expect( sources[ 0 ].attributes( 'src' ) ).toBe( wikipediaVideoSources[ 0 ].url );
		expect( sources[ 0 ].attributes( 'type' ) ).toBe( wikipediaVideoSources[ 0 ].type );
	} );
} );
