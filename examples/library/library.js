/**
 * 
 */

//Use mustache templates, not ERB-style
_.templateSettings = { interpolate : /\{\{(.+?)\}\}/g };

//---------------------------------------------------------------------
//Views
//---------------------------------------------------------------------

var Library_BookListView = Backbone.View.extend({
	//Render on collection load
	initialize: function(options) { this.collection.on('reset', this.render, this);  },
	render: function() {
		$(this.el).empty();
		this.collection.each(function(book) {
			//Render each book inside the list view
			var view = new Library_BookListItemView({model: book});
			$(this.el).append(view.render().el);
		}, this);
		return this;
	}
});

var Library_BookListItemView = Backbone.View.extend({
	tagName:'li',
	template: _.template(
		'<span>{{ title }}</span> -<span style="font-style:italic">{{ author }}</span> <a class="edit" href="#" onclick="return false">Edit</a>'
	),
	initialize: function(options) {
		this.model.on('change',this.render,this);
	},
	render: function() {
		console.log("Rendering book:", this.model.id);
		$(this.el).html(this.template(this.model.toJSON()));
		return this;
	},
	events: {
		'click .edit': 'handleEdit'
	},
	handleEdit: function() {
		console.log("Editing book:", this.model.id, "(not implemented yet)");
	}
});

//---------------------------------------------------------------------
//Initialization/application
//---------------------------------------------------------------------
//Store objects inside the Library namespace
var Library = {};
Library.views = {};
Library.i=0;

var Library_AppRouter = Backbone.Router.extend({
	routes: {
		'': "indexRoute",
		'books' : "booksRoute",
		'authors' : "authorsRoute",
		'*args' : "defaultRoute"
	},
	indexRoute: function() {
		console.log("Matching index route, redirecting");
		//$('#menu a[href="#books"]').click();
		Library.router.navigate('books', {trigger:true});
	},
	booksRoute: function() {
		console.log("Matching books route");
		//Build the collection view, attach it to the #books element
		if (!Library.books) {
			Library.books = new Library_BookCollection();
			//Build the collection view, attach it to the #books element
			Library.views.booklist = new Library_BookListView({collection:Library.books, el:$('#books_booklist')});
			
			//Load the books; when finished, the 'reset' event will trigger the view to render them.
			Library.books.fetch();	
		}
	},
	authorsRoute: function() {
		$('#authors').html("HI! "+ ++Library.i);
	}
});

$(function() {
	//Tabs Setup
    $('#menu a').on('click', function (e) { $(this).tab('show'); });
    //if (m=(window||this).location.href.match(/#(.*)$/)) $('#menu a[href="#'+m[1]+'"]').click();
	
	Library.router = new Library_AppRouter();
	Backbone.history.on('route', function(router, event, args) { 
		$('#menu a[href="#'+this.fragment+'"]').click();
		console.log("Route", this.fragment);
	});
	Backbone.history.start();
});
