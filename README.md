# PHP APk Parser

This package can extract application package files in APK format used by devices running on Android OS.
It can open an APK file and extract the contained manifest file to parse it and retrieve the meta-information it contains like the application name, description, device feature access permission it requires, etc..
The class can also extract the whole files contained in the APK file to a given directory.


## PHPClasses.org
----------
[Phpclasses.org Repo](http://www.phpclasses.org/apk-parser)

MiKandi Comments
----------------

This version modifies the file layout and names of the original project to be PSR-0 compliant (since we use both Symfony and another PSR-0 compliant framework). It was modified to fix the issue relating to [Unrecognized tag code 0x00100104](https://github.com/tufanbarisyildirim/php-apk-parser/issues/8)

