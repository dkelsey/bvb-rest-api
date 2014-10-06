# REST API

This is derived from the [WP-API](https://github.com/WP-API/WP-API) (which enables Access to your WordPress site's data through an easy-to-use HTTP REST API).  I've stripped out that functionality and added just the ability to upload, validate and process CSV files.  This is part of an Admin Console plugin I developed for validating and loading specificic CSVs into the underlying wordpress DB - into another DB Schema. 

## About

The WP-API is well documented in the original repo (please check it out).  I was creating an Admin Console
plugin to allow processing of batch and remittance reports (CSV files).  This had to run alongside 
and already running Wordpress Site and I knew I needed some sort of RESTful API.  I found this and decided to use it.

I cracked open the code and read their [Extending the APi](https://github.com/WP-API/WP-API).
I had very little trouble navigating the code and implementing what I wanted.

I wanted to enable uploaing CSVs, Validation, Processing, compressing, moving and deleting.
This repo contains contains that.

## Notes

I ran into difuculty with regards to nonces and authentication.  I haven't spent a year working with WordPress, thus i'm not familiar with all the inner workings and subdelties.  the API should run within a wordpress app with an anuthenticated Admin account.   They describe in their documentation three methods of authentication.  Basic authenication worked after installing the correct plugin--in development I used curl for testing.  The description of the use of nonces was short and not useful thus I simply hacked it out.   This API is secure via obfiscation.


[orriginal docs]: http://wp-api.org/
[orriginal GitHub]: https://github.com/WP-API/WP-API
