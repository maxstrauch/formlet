# formlet

The formlet library tries to separates HTML from application logic for PHP form. The background is fairly simple: for a project I needed a form generation library. There is a ton of libraries out there - just one click away from Google. Most popluar are Zend Framework and friends. But I needed something simple, quick and with no dependencies. Therefore **formlet** was born.

formlet tries to use the approach of JavaEE with an XML file which contains only HTML code. The formlet library parses this file and generates a form. Therefore no HTML code must be written as strings in PHP. The form inputs are represented by elements called `ui:text` and others. These are replaced. 

The library also does validation and is able to test if the form is submitted sucessfully or not. Furthermore form fields can be validated by certain parameters and also by callback functions for more complex conditions.

# Problems

The documentation is pretty poor since I've currently no time for it. I'll come back and fix the documentation but this might take some months. Currently there are some example which are pretty ugly (`example.php`, `example.xml`) and a very very very ugly documentation `poor-docs.txt`.

# License

[Creative Commons BY-SA 4.0](http://creativecommons.org/licenses/by-sa/4.0/)

This program is distributed WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 