.PHONY: test zip clean

test:
	composer test

zip: clean
	cd .. && zip -r whatconverts-metrics.zip whatconverts-metrics \
		-x "*/vendor/*" \
		-x "*/tests/*" \
		-x "*/composer.*" \
		-x "*/.git/*" \
		-x "*/phpunit.xml" \
		-x "*/.phpunit.cache/*" \
		-x "*/.gitignore" \
		-x "*/Makefile"
	@echo "Created ../whatconverts-metrics.zip"

clean:
	rm -f ../whatconverts-metrics.zip
