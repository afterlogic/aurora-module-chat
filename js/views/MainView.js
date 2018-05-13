'use strict';

var
	_ = require('underscore'),
	$ = require('jquery'),
	ko = require('knockout'),
	moment = require('moment'),
	
	TextUtils = require('%PathToCoreWebclientModule%/js/utils/Text.js'),
	Types = require('%PathToCoreWebclientModule%/js/utils/Types.js'),
	Utils = require('%PathToCoreWebclientModule%/js/utils/Common.js'),
	
	App = require('%PathToCoreWebclientModule%/js/App.js'),
	
	CAbstractScreenView = require('%PathToCoreWebclientModule%/js/views/CAbstractScreenView.js'),
	Popups = require('%PathToCoreWebclientModule%/js/Popups.js'),
	
	Ajax = require('modules/%ModuleName%/js/Ajax.js'),
	HeaderItemView = require('modules/%ModuleName%/js/views/HeaderItemView.js'),
	PostCheck = require('modules/%ModuleName%/js/PostCheck.js'),
	CreateChannelPopup = require('modules/%ModuleName%/js/popups/CreateChannelPopup.js'),
	AddUserPopup = require('modules/%ModuleName%/js/popups/AddUserPopup.js')
;

/**
 * View that is used as screen of chat module. Inherits from CAbstractScreenView that has showing and hiding methods.
 * 
 * @constructor
 */
function CChatView()
{
	CAbstractScreenView.call(this, '%ModuleName%');
	
	/**
	 * Text for displaying in browser title when chat screen is shown.
	 */
	this.browserTitle = ko.observable(TextUtils.i18n('%MODULENAME%/HEADING_BROWSER_TAB'));
	
	this.bAllowReply = (App.getUserRole() === Enums.UserRole.NormalUser);
	this.channels = ko.observableArray([]);
	this.gettingMore = ko.observable(false);
	this.postsPerPage = 10;
	// Quick Reply Part
	this.domQuickReply = ko.observable(null);
	this.replyText = ko.observable('');
	this.replyTextFocus = ko.observable(false);
	this.replySendingStarted = ko.observable(false);
	this.replySavingStarted = ko.observable(false);
	this.replyAutoSavingStarted = ko.observable(false);
	
	this.isQuickReplyActive = ko.computed(function () {
		return this.replyText().length > 0 || this.replyTextFocus();
	}, this);
	this.replyLoadingText = ko.computed(function () {
		if (this.replySendingStarted())
		{
			return TextUtils.i18n('COREWEBCLIENT/INFO_SENDING');
		}
		else if (this.replySavingStarted())
		{
			return TextUtils.i18n('MAIL/INFO_SAVING');
		}
		return '';
	}, this);
	
	this.saveButtonText = ko.computed(function () {
		return this.replyAutoSavingStarted() ? TextUtils.i18n('MAIL/ACTION_SAVE_IN_PROGRESS') : TextUtils.i18n('MAIL/ACTION_SAVE');
	}, this);
	
	this.scrolledPostsDom = ko.observable(null);
	this.scrollTrigger = ko.observable(0);
	this.bBottom = false;
	this.replyTextFocus.subscribe(function () {
		this.scrollIfNecessary(500);
	}, this);
	App.broadcastEvent('%ModuleName%::ConstructView::after', {'Name': this.ViewConstructorName, 'View': this});
	this.bigButtonCommand = Utils.createCommand(this, function () {
		Popups.showPopup(CreateChannelPopup, [_.bind(function () { this.getChannels(); }, this)]);
	});
	this.addUserCommand = Utils.createCommand(this, this.addUser);
	this.selectedChannel = ko.observable(null);
	this.currentChannelPosts = ko.computed(function () {
		if (this.selectedChannel())
		{
			return this.selectedChannel().PostsCollection();
		}
		return [];
	}, this);
}

_.extendOwn(CChatView.prototype, CAbstractScreenView.prototype);

CChatView.prototype.ViewTemplate = '%ModuleName%_MainView';
CChatView.prototype.ViewConstructorName = 'CChatView';

/**
 * Scrolls post list to bottom after posts getting if it was scrolled to bottom earlier.
 * 
 * @param {int} iDelay delay for scrolling in milliseconds.
 */
CChatView.prototype.scrollIfNecessary = function (iDelay)
{
	if (this.scrolledPostsDom() && this.scrolledPostsDom()[0])
	{
		var oScrolledPostsDom = this.scrolledPostsDom()[0];
		this.bBottom = (oScrolledPostsDom.clientHeight + oScrolledPostsDom.scrollTop) <= oScrolledPostsDom.scrollHeight;
	}
	
	if (this.bBottom)
	{
		_.delay(_.bind(function () {
			this.scrollTrigger(this.scrollTrigger() + 1);
		}, this), iDelay);
	}
};

/**
 * Called every time when screen is shown. Requests posts count from server at first.
 */
CChatView.prototype.onShow = function ()
{
	//After showing chat tab we stopping service which checking for new posts
	PostCheck.stopCheck();
	HeaderItemView.isUnseen(false);

	if (this.channels().length <= 0)
	{
		Ajax.send('GetUserChannelsWithPosts',
			{
				'Limit': this.postsPerPage
			},
			function (oResponse) {
				if (oResponse.Result && oResponse.Result.Collection && !_.isEmpty(oResponse.Result.Collection))
				{
					this.initChannelPosts(oResponse.Result.Collection);
				}
				this.getLastPosts();
			},
			this
		);
	}
//	this.getChannels();
};

/**
 * Changes posts offset and request them with new value to get earlier posts.
 */
CChatView.prototype.showMore = function ()
{
	if (this.selectedChannel().Offset() > 0)
	{
		this.gettingMore(true);
	}
	this.selectedChannel().Offset((this.selectedChannel().Offset() >= this.postsPerPage) ? this.selectedChannel().Offset() - this.postsPerPage : 0);
	this.selectedChannel().PostsOnPage(this.selectedChannel().PostsOnPage() + this.postsPerPage);
	this.getPreviousPosts();
};

CChatView.prototype.getLastPosts = function ()
{
	Ajax.send('GetLastPosts', {'IsUpdateLastShowPostsDate':HeaderItemView.isCurrent()}, this.onGetLastPostsResponse, this, /*iTimeout*/30000);
};

CChatView.prototype.getPreviousPosts = function ()
{
	var $aOffsets = {};

	$aOffsets[this.selectedChannel().UUID] = this.selectedChannel().Offset();

	Ajax.send('GetPosts', {
			Offsets: $aOffsets,
			Limit: this.selectedChannel().Offset() > 0 ? this.postsPerPage : this.selectedChannel().PostsCount() % this.postsPerPage,
			ChannelUUID: this.selectedChannel().UUID
		},
		this.onGetPreviousPostsResponse,
		this);
};

/**
 * Prepares display values of text and date fields. Broadcasts event before post displaying.
 * Adds prepared post into posts array.
 * 
 * @param {Object} oPost Post object.
 * @param {boolean} bEnd Indicates if post should be added to the end of the posts array or to the its beginning.
 * @param {boolean} bOwn Indicates own post.
 */
CChatView.prototype.addPost = function (oPost, bEnd, bOwn)
{
	var oCahnnel = this.getChannelByUUID(oPost.channelUUID);

	oPost.displayDate = this.getDisplayDate(moment.utc(oPost.date));
	oPost.displayText = oPost.is_html ? oPost.text : TextUtils.encodeHtml(oPost.text);
	oPost.isOwn = bOwn;

	App.broadcastEvent('Chat::DisplayPost::before', {'Post': oPost, 'Own': bOwn});

	if (oCahnnel && oCahnnel.PostsCollection)
	{
		if (bEnd)
		{
			oCahnnel.PostsCollection.push(oPost);
		}
		else
		{
			oCahnnel.PostsCollection.unshift(oPost);
		}
	}
};

CChatView.prototype.onGetPreviousPostsResponse = function (oResponse, oRequest)
{
	if (oResponse.Result && Types.isNonEmptyArray(oResponse.Result.Collection))
	{
		var
			aPosts = oResponse.Result.Collection,
			iLastIndex = aPosts.length - 1
		;

		/**
		* Adds all new posts to the beginning of the post list.
		*/
		for (var iIndex = iLastIndex; iIndex >= 0; iIndex--)
		{
			this.addPost(aPosts[iIndex], false, aPosts[iIndex].userId === App.getUserId());
		}

		if (!HeaderItemView.isCurrent())
		{
			HeaderItemView.isUnseen(true);
		}
	}
	this.gettingMore(false);
};

/**
 * Removes recent own posts that were added between posts requests.
 */
CChatView.prototype.removeOwnPosts = function (oChannel)
{
	for (var i = 0; i < oChannel.PostsCollection().length; i++)
	{
		if (oChannel.PostsCollection()[i].recent)
		{
			oChannel.PostsCollection.remove(oChannel.PostsCollection()[i]);
		}
	}
};

/**
 * Formats date for displaying.
 * 
 * @param {Object} oMomentUtc Moment date object in utc.
 */
CChatView.prototype.getDisplayDate = function (oMomentUtc)
{
	var
		oLocal = oMomentUtc.local(),
		oNow = moment()
	;
	
	if (oNow.diff(oLocal, 'days') === 0)
	{
		return oLocal.format('HH:mm');
	}
	else
	{
		return oLocal.format('MMM Do HH:mm');
	}
};

/**
 * Sends request to the server for creating post.
 * 
 * @returns {Boolean} Prevents bubbling of keyup event.
 */
CChatView.prototype.sendPost = function ()
{
	if (this.bAllowReply && $.trim(this.replyText()) !== '')
	{
		var sDate = moment().utc().format('YYYY-MM-DD HH:mm:ss');
		Ajax.send('CreatePost', {'Text': this.replyText(), 'ChannelUUID': this.selectedChannel().UUID}, false, this);
		this.addPost({
				userId: App.getUserId(),
				name: App.getUserPublicId(),
				text: this.replyText(),
				date: sDate,
				recent: true,
				channelUUID: this.selectedChannel().UUID
			},
			true,
			true
		);
		this.replyText('');
	}
	return false;
};

CChatView.prototype.onGetLastPostsResponse = function (oResponse, oRequest)
{
	if (oResponse.Result && Types.isNonEmptyArray(oResponse.Result.Collection))
	{
		var
			aPosts = oResponse.Result.Collection,
			iFirstIndex = 0
		;
		_.each(this.channels(), _.bind(function (oChannel) {
				this.removeOwnPosts(oChannel);
			},this));
		/**
		 * Adds all new posts to the end of the post list.
		 */
		for (var iIndex = iFirstIndex; iIndex < aPosts.length; iIndex++)
		{
			this.addPost(aPosts[iIndex], true, aPosts[iIndex].userId === App.getUserId());
		}
		this.scrollIfNecessary(500);

		_.each(this.channels(), _.bind(function (oChannel) {
			this.removeExtraPosts(oChannel.UUID);
		},this));
		if (!HeaderItemView.isCurrent())
		{
			HeaderItemView.isUnseen(true);
		}
	}
	this.gettingMore(false);
	setTimeout(_.bind(this.getLastPosts, this),1000);
};

/**
 * Hide posts if it's count bigger then postsPerPage + offset 
 * @param {type} ChannelUUID
 * @returns {undefined}
 */
CChatView.prototype.removeExtraPosts = function (ChannelUUID)
{
	var
		oChannel = this.getChannelByUUID(ChannelUUID),
		iNumberOfExtraPosts = oChannel.PostsCollection().length - oChannel.PostsOnPage()
	;

	if (iNumberOfExtraPosts > 0)
	{
		for (var i = 0; i < iNumberOfExtraPosts; i++)
		{
			oChannel.PostsCollection.remove(oChannel.PostsCollection()[i]);
		}
		oChannel.Offset(Types.pInt(oChannel.Offset()) + iNumberOfExtraPosts);
	}
};

CChatView.prototype.setTextFocus = function ()
{
	$('#reply_text').focus();
};

CChatView.prototype.showChannel = function (UUID)
{
	this.selectedChannel(this.getChannelByUUID(UUID));
};

CChatView.prototype.initChannelPosts = function (oChannelData)
{
	for (var ChannelUUID in oChannelData)
	{
		this.channels.push({
			'Offset': ko.observable(oChannelData[ChannelUUID]['PostCount'] > this.postsPerPage ? oChannelData[ChannelUUID]['PostCount'] - this.postsPerPage : 0),
			'PostsCount': ko.observable(oChannelData[ChannelUUID]['PostCount']),
			'Name': oChannelData[ChannelUUID]['Name'],
			'PostsOnPage': ko.observable(this.postsPerPage),
			'PostsCollection': ko.observableArray([]),
			'UUID': ChannelUUID
		});
		_.each(oChannelData[ChannelUUID]['PostsCollection'], _.bind(function (oPost) {
			this.addPost(oPost, true, oPost.userId === App.getUserId());
		}, this));
		if (!this.selectedChannel())
		{
			this.selectedChannel(this.getChannelByUUID(ChannelUUID));
		}
	}
};

CChatView.prototype.addUser = function ()
{
	Popups.showPopup(AddUserPopup, [
		_.bind(function () { console.log("addUser"); }, this),
		this.selectedChannel().UUID
	]);
};

CChatView.prototype.getChannelByUUID = function (UUID)
{
	return _.find(this.channels(), function(oChannel){
		return oChannel.UUID === UUID; 
	});
};

module.exports = new CChatView();
