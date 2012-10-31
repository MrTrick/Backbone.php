/**
 * 
 */

//Use mustache templates, not ERB-style
_.templateSettings = { interpolate : /\{\{(.+?)\}\}/g };

//Namespace for the application
var App = window.App = {};

//Helper to do temporary alert messages
App.alert = function(type, message) {
	var alert = $('<div class="alert alert-'+type+'">'+message+'</div>');
	alert.slideDown(150).delay(1000).slideUp(500, function() {$(this).remove();} ); 
	$('#main').prepend(alert);
};

///////////////////////////////////////////////////////////////////////////////
//Track server time

// Store the offset between server and client time
App.server_time_offset = 0;
// Every time a response is received, calculate/store the difference between server time and client time
$(document).ajaxComplete(function(event,xhr) {
	var date_str = xhr.getResponseHeader("Date");
	if (!date_str) { console.log("Response didn't contain Date"); return; }
	App.server_time_offset = Math.round( (new Date(date_str).valueOf() - new Date().valueOf()) / 1000 );
});
// Return the time on the server, in seconds since epoch
App.getServerTime = function() { 
	return Math.round(Date.now()/1000) + App.server_time_offset; 
};

///////////////////////////////////////////////////////////////////////////////
//Session
// Responsible for: 
//  - Tracking whether or not the user is logged in
//  - Storing the client's credentials
//  - Signing all requests so the server will accept them
//  - Automatically signing the user out when their credentials expire
//  - Generating 'login' and 'logout' requests
App.Session = Backbone.Model.extend({
	url: 'auth/login',
	defaults: {
		client_key: null,
		expiry: null,
		identity: null
	},
	//Return whether the user is signed in
	isAuthenticated: function() {
		var expiry = this.get('expiry');
		return this.get('identity') && expiry && expiry > App.getServerTime();
	},
	//Perform a login operation.
	// Any credentials required for authenticating should be included in the data:{} object in the options parameter.
	// (For this example it's username, password. Other Zend_Auth_Adapters might require a different a set of info)
	login: function(options) {
		options = options ? _.clone(options) : {};
		var success = options.success;
		options.success = function(sess, resp, options) {
			//Set up a trigger to log the user out when their key expires
			if (sess.timeout_trigger) clearTimeout(sess.timeout_trigger);
			sess.timeout_trigger = setTimeout(_.bind(sess.logout, sess), (sess.get('expiry') - App.getServerTime())*1000); 
			
			//On successful login, trigger a login(session, identity, options) event
			sess.trigger('login', sess, sess.get('identity'), options);
			if (success) success(sess, resp, options);
		};
		//Send the authentication request
		this.fetch(options);
		
		return this;
	},
	//Perform a logout operation.
	// This will clear the session, and trigger the logout event.
	// A 'logout' request is sent to the server, but for HMAC-signed sessions it's not really needed.
	logout: function(options) {
		var identity = this.get('identity');
		if (!identity) return; //Already logged out, don't trigger again.
		
		//Clear all local information about the session
		this.attributes = {};
		this._previousAttributes = {};
		
		//On logout, trigger a logout(model, identity, options) event
		this.trigger('logout', this, identity, options);
		
		//Notify the server that we're logging out, don't read the response
		$.ajax('auth/logout', {data:{identity:identity}});
	}
});
App.session = new App.Session();

//This function hooks into every request before it's sent.
//If there is an authenticated session, will 'sign' the request.
$(document).ajaxSend(function(event,xhr,params) {
	if (App.session.isAuthenticated()) {
		var method = params['type'];
		var base = window.location.href.substring(0, window.location.href.length - window.location.search.length);
		var url = base + params['url']; //The absolute url for the request TODO: Make more reliable
		var signature = CryptoJS.HmacSHA256(method+url, App.session.get('client_key'));
		var auth_block = {
			identity: App.session.get('identity'),
			expiry: App.session.get('expiry'),
			signature: signature.toString()
		};
		xhr.setRequestHeader('Authorization', 'HMAC '+$.param(auth_block)); 
	}
});

///////////////////////////////////////////////////////////////////////////////
//Login/Logout view/actions
// This view renders the login form or the 'Logged in, logout?' message based on the 
// session state.
// It also:
//  Handles the imperative actions from the user to login, or logout
//  Listens to the session events to automatically re-render when necessary
// 
App.LoginForm = Backbone.View.extend({
	initialize: function(options) {
		App.session.on('login logout', this.render, this);
		this.render();
	},
	//Rendering
	//----------------
	templates: {
		form: _.template(
		  '<form class="navbar-form">'+
            '<input class="span2" type="text" name="username" placeholder="User Name"> '+
            '<input class="span2" type="password" name="password" placeholder="Password"> '+
            '<button id="login_btn" type="submit" class="btn">Log in</button>'+
          '</form>'),
        logged_in: _.template(
          '<div>'+
            '<p class="navbar-text pull-left">Logged in as {{ identity }}. </p> '+
              '<a id="logout_btn" class="btn pull-left">Log out</a>'+
          '</div>') 
	},
	render: function() {
		if (App.session.isAuthenticated()) {
			this.$el.html(this.templates.logged_in({ identity: App.session.get('identity') }));
		} else {
			this.$el.html(this.templates.form());
		}
		return this;
	},
	//Event Handling
	//----------------	
	events: {
		'submit form': 'doLogin',
		'click #logout_btn' : 'doLogout'
	},
	//Collect the username/password from the form, do a basic check, and pass to 
	//the session login function.
	//If the request fails, grab the server response and print it.  
	doLogin: function(e) {
		e.stopPropagation();
		e.preventDefault();
		
		var username = $("input[name=username]").val();
		var password = $("input[name=password]").val();
		if (!username || !password) return App.alert('error', 'Missing username or password'); 
		
		App.session.login({
			data:{username:username, password:password},
			error: _.bind(function(sess, xhr, options) {
				if (xhr.status==500) 
					App.alert('error', 'Server error, please try again later.');
				else
					App.alert('error', JSON.parse(xhr.responseText).error);
			}, this)
		});
	},
	//Delegates to App.session
	doLogout: function(e) {
		e.stopPropagation();
		e.preventDefault();
		App.session.logout();
	}
});

///////////////////////////////////////////////////////////////////////////////
// Views

App.PersonView = Backbone.View.extend({
	template: _.template(
	    "<dl>" +
	    "  <dt>Username</dt><dd>{{ username }}</dd>" +
	    "  <dt>First Name</dt><dd>{{ first_name }}</dd>" +
	    "  <dt>Surname</dt><dd>{{ surname }}</dd>" +
	    "  <dt>E-Mail</dt><dd>{{ email }}</dd>" +
	    "</dl>"
	),
	render: function() {
		this.$el.html( this.template( this.model.attributes )); 
	}
});

App.PersonCollectionView = Backbone.View.extend({
	template: _.template(
	   "<h3>People</h3>" +
	   "<div style='border:1px black solid; padding:1em;'></div>"
	),
	initialise: function(options) {
		options.collection.on('add', this.addOne, this);
	},
	render: function() {
		this.$el.html( this.template() );
		this.inner = this.$el.find('div');
		this.collection.each(this.addOne, this);
	},
	addOne: function(model) {
		personview = new App.PersonView({model:model});
		personview.render();
		this.inner.append(personview.el);
		this.inner.append('<hr>');
		model.on('remove', personview.remove, personview);
	}
});

// Identity view; shows the identity if authenticated, blank if not
AE_Identity = AE_PersonModel.extend({url:function() { return 'auth/identity'; }});

App.IdentityView = Backbone.View.extend({
	template: _.template(
	   "<h3>Identity</h3>" +
	   "<div style='border:1px black solid; padding:1em;'></div>"
	),
	render: function() {
		this.$el.html(this.template());
		var inner = this.$el.find('div');
		var identity = new AE_Identity();
		identity.fetch({
			success:function(identity) {
				//Delegate through to a PersonView renderer				
				personview = new App.PersonView({model:identity});
				personview.render();
				inner.append(personview.el);	
			},
			error:function(identity,xhr) {
				msg = JSON.parse(xhr.responseText).error;
				inner.append('<div class="alert alert-error"><strong>Error</strong> '+msg+'</div>');				
			}
		});
	}
});

///////////////////////////////////////////////////////////////////////////////
//Application

$(function() {
	App.login_form = new App.LoginForm({el: '#login_form_container', session: App.session});
	//Notify when we login or logout successfully:
	App.session.on('login', function(sess,id) { App.alert('info', 'Logged in. Hi '+id+'!'); });
	App.session.on('logout', function(sess,id) { App.alert('info', 'Logged out. Bye, '+id+'.'); });
	
	//Show the current identity
	var identity_view = new App.IdentityView({el: '#identity_container'});
	App.session.on('login logout', identity_view.render, identity_view);
	identity_view.render();
	
	//Show the people
	var collection = new AE_PersonCollection();
	var collection_view = new App.PersonCollectionView({el: '#people_container', collection:collection});
	collection.on('all', collection_view.render, collection_view);
	collection_view.render();
	App.session.on('login', function() {
		collection.fetch({
			error:function(coll,xhr) {
				collection.reset();
				App.alert( 'error', '<strong>Error</strong> ' + JSON.parse(xhr.responseText).error );
			}
		});
	});
	App.session.on('logout', function() { 
		collection.reset();
	});
	
	
	
	
	//TODO: Add actions to access public and private content.

});