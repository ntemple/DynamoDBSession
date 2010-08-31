Testing
=======

In order to run the test cases you need to have a locally running MongoDB server with auth disabled. You also need to have PHPUnit installed.

To run the tests execute the following command:

$ phpunit --bootstrap TestBootstrap.php MongoSession.test.php
