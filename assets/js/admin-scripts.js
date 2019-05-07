/**
 * WP Content Pilot Admin
 * https://www.pluginever.com
 *
 * Copyright (c) 2019 pluginever
 * Licensed under the GPLv2+ license.
 */

/*jslint browser: true */
/*global jQuery:false */
jQuery(document).ready(function ($, window, document, undefined) {
	'use strict';
	$.wp_content_pilot = {

		init: function () {
			$('#_campaign_type').on('change', this.triggerCampaignTypeChange);
			$('body').on('change', '#_post_from', this.toggleKeywordsField);
			$('body').bind('campaign_type_changed', this.getCampaignOptions);
			$('body').bind('campaign_type_changed', this.getCampaignAllTemplateTags);
			$('body').bind('campaign_type_changed', this.getCampaignDefaultTemplateTags);
			$('body').on('click', '.wpcp-delete-all', this.deleteAllPostedPosts);

			this.repeatableInput();
		},

		initPlugins: function () {
			$('.ever-select-chosen').chosen({
				inherit_select_classes: true,
				// placeholder_text_single: edd_vars.one_option,
				// placeholder_text_multiple: edd_vars.one_or_more_option,
			});
		},

		triggerCampaignTypeChange: function () {
			var campaign_type = $(this).val();
			var post_id = $('#post_ID').val();
			if (campaign_type && post_id) {
				var data = {
					campaign_type: campaign_type,
					post_id: post_id,
					type: campaign_type,
				};
				$('body').trigger('campaign_type_changed', data);
			}
		},

		getCampaignOptions: function (e, data) {
			wp.ajax.send({
				data: {
					action: 'wpcp_get_campaign_options_metabox_content',
					campaign_type: data.type,
					post_id: data.post_id,
					nonce: '',
				},
				success: function (res) {
					$('#campaign-options .inside').html(res);
				},
				error: function (error) {
					alert('Something happend wrong');
					console.log(error);
				}
			});

		},

		getCampaignAllTemplateTags: function (e, data) {
			wp.ajax.send({
				data: {
					action: 'wpcp_get_campaign_template_tags_metabox_content',
					campaign_type: data.type,
					post_id: data.post_id,
					nonce: '',
				},
				success: function (res) {
					$('#campaign-template-tags .inside').html(res);
				},
				error: function (error) {
					alert('Something happend wrong');
					console.log(error);
				}
			});
		},

		repeatableInput: function () {
			var date = Date.now();
			var getUniqId = function () {
				date++;
				return date.toString(36);
			};
			$('body').on('click', '.wpcp-add-field', function (e) {
				e.preventDefault();
				var tmplID          = $(this).data('tmpl'),
					template        = $('#' + tmplID),
					fieldsContainer = $(this).prev(),
					html            = template.html();

				html = html.replace(/ITEM_ID/g, getUniqId());

				fieldsContainer.append(html);
			});

			$('body').on('click', '.wpcp-repeatable-delete', function (e) {
				e.preventDefault();
				var item = $(this).parents('.wpcp-repeatable-field');

				if (confirm('Yes delete it')) {
					item.remove();
				}
			});
		},

		deleteAllPostedPosts: function (e) {
			e.preventDefault();

			if (!confirm('Are you sure?')) {
				return;
			}

			var $el     = $(this),
				spinner = $(this).next(),
				camp_id = $el.data('camp-id'),
				nonce   = $el.data('nonce');

			$el.attr('disabled', true);
			spinner.addClass('active');

			wp.ajax.send({
				data: {
					action: 'wpcp_delete_all_posts_by_campaign_id',
					camp_id: camp_id,
					nonce: nonce,
				},
				success: function (res) {
					spinner.removeClass('active');
					$el.attr('disabled', false);
					// $('#campaign-template-tags .inside').html(res);
				},
				error: function (error) {
					spinner.removeClass('active');
					$el.attr('disabled', false);
					alert('Something happend wrong');
					console.log(error);
				}
			});
		},

		getCampaignDefaultTemplateTags: function (e, data) {
			wp.ajax.send({
				data: {
					action: 'wpcp_get_campaign_default_template_tags',
					campaign_type: data.type,
					post_id: data.post_id
				},

				success: function (res) {
					console.log(res);
					$('#_post_template').text(res);
				},

				error: function (error) {
					alert('Something happend wrong');
					console.log(error);
				}
			});
		},

		toggleKeywordsField: function (e) {

			var $keywords_input = $('#_keywords'),
				$keywords_field = $('._keywords_field'),
				$post_from      = $('#_post_from').val();

			if ('global' !== $post_from) {
				$keywords_field.hide('fast');
				$keywords_input.removeAttr('required');
			} else {
				$keywords_field.show('fast');
				$keywords_input.attr('required', true);
			}

		}
	};


	$.wp_content_pilot.init();
	$.wp_content_pilot.initPlugins();
	$.wp_content_pilot.toggleKeywordsField();
});
