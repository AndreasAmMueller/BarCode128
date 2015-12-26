# DIRS
DOCUMENTATION = docs
SOURCE = src
TOOLS = tools
TESTS = tests

.PHONY: all test docs clean

all: clean docs

test:
	php $(TOOLS)/phpunit.phar --verbose $(TESTS)/HiOrgApiTest

docs: clean
	php $(TOOLS)/phpDocumentor.phar -p --template="clean" -d $(SOURCE)/ -t $(DOCUMENTATION)/

clean:
	rm -rf $(DOCUMENTATION)/*
