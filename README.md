Backbone.php
============

A high-performance API server implementation inspired heavily by Backbone.js.
Used in production for complex internal apps.

Implements a similar design philosophy to Backbone.js:

 - Models and Collections
 - Modular 'Sync' backend connectors
 - Configuration through dependency injection
 - Event subscription
 - Cascading changes

And adds further features:

 - Chainable; Routers chain and provide context to further invocations.
 - Testable; DI makes isolation of any code simple.
 - Cross-Language Export; PHP Models and Collections export to Backbone.js class definitions.


