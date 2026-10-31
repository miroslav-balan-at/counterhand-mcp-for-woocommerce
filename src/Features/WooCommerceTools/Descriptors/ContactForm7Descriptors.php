<?php

declare( strict_types=1 );

namespace Counterhand\Features\WooCommerceTools\Descriptors;

use Counterhand\Features\Tokens\Domain\IntegrationGroup;
use Counterhand\Features\WooCommerceTools\Domain\ContactFormPolicy;
use Counterhand\Features\WooCommerceTools\Domain\DescriptorProvider;
use Counterhand\Features\WooCommerceTools\Domain\FieldProfile;
use Counterhand\Features\WooCommerceTools\Domain\Operation;
use Counterhand\Features\WooCommerceTools\Domain\OperationDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ResourceDescriptor;
use Counterhand\Features\WooCommerceTools\Domain\ToolName;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRequestGuards;
use Counterhand\Features\WooCommerceTools\Infrastructure\RestRoute;

defined( 'ABSPATH' ) || exit;

/**
 * Contact Form 7's forms: /contact-form-7/v1/contact-forms.
 *
 * Its routes declare no arguments at all, so unlike every other resource here
 * the inputs are written down, from wpcf7_save_contact_form() and
 * get_contact_forms() as of CF7 6.2. Writes stay open, so a field a later release
 * adds can still be sent, and integrations.spec.ts runs against the latest release
 * to catch what a copy cannot. The feedback and refill routes stay out: they are
 * the public form submission path, not administration.
 */
final readonly class ContactForm7Descriptors implements DescriptorProvider {

	private const NAMESPACE = 'contact-form-7/v1';

	private const SUMMARY_FIELDS = [ 'id', 'slug', 'title', 'locale' ];

	/**
	 * The properties by name rather than whole, because any add-on can add its own
	 * keys there, and some keep credentials.
	 */
	private const ITEM_FIELDS = [
		'id',
		'slug',
		'title',
		'locale',
		'properties.form',
		'properties.mail',
		'properties.mail_2',
		'properties.messages',
		'properties.additional_settings',
		'config_errors',
	];

	private const SAVING = [
		'context'                                 => 'save',
		RestRequestGuards::NO_UNFILTERED_HTML     => 1,
		RestRequestGuards::KEEP_FORM_INTEGRATIONS => 1,
	];

	/** @return list<ResourceDescriptor> */
	public function resources(): array {
		return [
			new ResourceDescriptor(
				'contact_forms',
				IntegrationGroup::of( 'contact-form-7', copy: new ContactForm7Copy() ),
				RestRoute::of( self::NAMESPACE, '/contact-forms' ),
				RestRoute::of( self::NAMESPACE, '/contact-forms/{id}' ),
				'contact form',
				'contact forms',
				$this->operations()
			),
		];
	}

	/** @return list<OperationDescriptor> */
	private function operations(): array {
		return [
			new OperationDescriptor(
				ToolName::from( 'get_contact_forms' ),
				Operation::GetItems,
				new FieldProfile( [], self::SUMMARY_FIELDS, false ),
				description_override: 'List Contact Form 7 forms. Pages by offset and per_page; the response carries no totals, so keep going until a page comes back short. Submissions are not here: Contact Form 7 does not store them.',
				declared_arguments: [
					'per_page' => [
						'type'        => 'integer',
						'description' => 'How many forms to return.',
						'default'     => 20,
						'minimum'     => 1,
						'maximum'     => 100,
					],
					'offset'   => [
						'type'        => 'integer',
						'description' => 'How many forms to skip.',
						'minimum'     => 0,
					],
					'search'   => [
						'type'        => 'string',
						'description' => 'Limit to forms matching this text.',
					],
					'order'    => [
						'type' => 'string',
						'enum' => [ 'ASC', 'DESC' ],
					],
					'orderby'  => [
						'type'        => 'string',
						'description' => 'A WP_Query orderby value, such as title or date.',
					],
				]
			),
			new OperationDescriptor(
				ToolName::from( 'get_contact_form' ),
				Operation::GetItem,
				new FieldProfile( [], self::ITEM_FIELDS, false ),
				'properties holds the form template, both mail templates, the messages and the additional settings. To write form or additional_settings back, send the string in their .content, not the object. Reading needs permission to edit forms, not just to list them.'
			),
			new OperationDescriptor(
				ToolName::from( 'create_contact_form' ),
				Operation::CreateItem,
				new FieldProfile( [], self::ITEM_FIELDS ),
				'Anything you leave out starts from Contact Form 7\'s default template, in the connected user\'s language rather than locale, and that template\'s mail expects fields named your-name and your-subject. config_errors lists what its validator found wrong; empty can also mean validation is switched off.',
				forced_params: self::SAVING,
				// The public feedback route can send mail from any form that exists,
				// so a new form is as risky as an edited one.
				requires_confirmation: true,
				policy: new ContactFormPolicy(),
				declared_arguments: $this->writable_arguments()
			),
			new OperationDescriptor(
				ToolName::from( 'update_contact_form' ),
				Operation::UpdateItem,
				new FieldProfile( [], self::ITEM_FIELDS ),
				'mail, mail_2, messages and additional_settings each replace the stored value whole, so read the form first and send everything you want to keep, including any attachments. To change a recipient or additional_headers such as Bcc is to change who receives every future submission: say so plainly. A title change also renames its Flamingo channel. config_errors lists what its validator found wrong; empty can also mean validation is switched off.',
				forced_params: self::SAVING,
				// Changing a recipient quietly redirects every future submission.
				requires_confirmation: true,
				policy: new ContactFormPolicy(),
				declared_arguments: $this->writable_arguments()
			),
			new OperationDescriptor(
				ToolName::from( 'delete_contact_form' ),
				Operation::DeleteItem,
				new FieldProfile( [], [ 'deleted' ], false ),
				description_override: 'Permanently delete a Contact Form 7 form. There is no trash, and any page embedding it stops showing a form.',
				requires_confirmation: true
			),
		];
	}

	/** @return array<string, array<string, mixed>> */
	private function writable_arguments(): array {
		$mail = [
			'type'          => 'object',
			// CF7 blanks every field of a mail it is sent empty.
			'minProperties' => 1,
			'properties'    => [
				'subject'            => [ 'type' => 'string' ],
				'sender'             => [ 'type' => 'string' ],
				'recipient'          => [ 'type' => 'string' ],
				'body'               => [ 'type' => 'string' ],
				'additional_headers' => [ 'type' => 'string' ],
				'attachments'        => [ 'type' => 'string' ],
				'use_html'           => [ 'type' => 'boolean' ],
				'exclude_blank'      => [ 'type' => 'boolean' ],
			],
		];

		return [
			'title'               => [ 'type' => 'string' ],
			'locale'              => [
				'type'        => 'string',
				'description' => 'A WordPress locale, such as de_DE.',
			],
			'form'                => [
				'type'        => 'string',
				'description' => 'A string: the form template in Contact Form 7 form-tag syntax, such as [email* your-email]. This is the .content of what get_contact_form returned. Script is removed; compare the returned form.content.',
			],
			'mail'                => [
				'description' => 'The mail sent when the form is submitted. Sending this also switches it on; skip_mail in additional_settings is the only way off.',
				...$mail,
			],
			'mail_2'              => [
				'description'   => 'The optional second mail, usually an autoresponder. Stays off unless active is true.',
				'type'          => 'object',
				'minProperties' => 1,
				'properties'    => [
					'active' => [ 'type' => 'boolean' ],
					...$mail['properties'],
				],
			],
			'messages'            => [
				'type'                 => 'object',
				'description'          => 'The messages shown to the person filling in the form, by message id. Any id you leave out becomes blank, so send them all.',
				'minProperties'        => 1,
				'additionalProperties' => [ 'type' => 'string' ],
			],
			'additional_settings' => [
				'type'        => 'string',
				'description' => 'A string: one setting per line, as name: value, replacing all of them. This is the .content of what get_contact_form returned. Careful: demo_mode: on reports success but sends and stores nothing, skip_mail: on sends nothing, do_not_store: on stops Flamingo, subscribers_only: on hides the form from visitors.',
			],
		];
	}
}
