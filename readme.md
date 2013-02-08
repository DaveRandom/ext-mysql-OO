#You are Doing It Wrong™

[**Please, don't use `mysql_*` functions in new code**](http://bit.ly/phpmsql). They are no longer maintained [and are officially deprecated](http://j.mp/XqV7Lp). See the [**red box**](http://j.mp/Te9zIL)? Learn about [*prepared statements*](http://j.mp/T9hLWi) instead, and use [PDO](http://php.net/pdo) or [MySQLi](http://php.net/mysqli) - [this article](http://j.mp/QEx8IB) will help you decide which. If you choose PDO, [here is a good tutorial](http://j.mp/PoWehJ).

---

This small library aims to provide a simple, PDO-like wrapper for ext/mysql.

##What it is

It attempts to provide a more OO-friendly interface in those dire times of need, and counters a few of the common user input sanitisation mistakes by providing a very simple prepared statement **emulation**.

##What it is not

There are several differences between this API and PDO so read the codez, not the manual for a different API.

The API *looks* like it supports prepared statements - it doesn't. It's just simple string manipulation made more complicated.

The query is not parsed at all, and as a result there are two major limitations, as well as many smaller issues:
 - It only supports `:named` placeholders. Question marks are not supported.
 - It is basically a glorified (and not very intelligent) search and replace, so putting something that `"looks :like a placeholder"` in a string literal will cause problems.

---

**Don't use this** unless you absolutely have no option but to use ext/mysql. If you do use it, make sure you understand the problems it is attempting to solve before you start.

Requires PHP 5.3+, but only really because of the namespacing. If you remove the namespace declaration from the head of the files and the leading backslashes from exception names, it should work (more or less) on any PHP5 installation.