# QCubed-4 Cache
This is an independent caching library written in PHP with focus on QCubed - v4. 

## Goal

The overall goal is: 

Create a caching library in its own standalone repo, and have that library implement PSR-16 for SimpleCaching, as well as PSR-1,2 and 4. The library should be able to encapsulate your Redis work, as well the APC and APCu caches and Memcache. Caches should be clearable, and when possible, have a subdomain be clearable (I don’t think this is doable with APC, since its just a simple cache. Not sure on the other ones). Configuration should be done through the config library mentioned above. Should also be able to use just a plain-old PHP array as a cache, but with read and write functions to persist the cache on disk (similar to QCache).


