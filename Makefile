.PHONY: fix fix-file fix-changed docs-install docs-dev docs-build docs-preview

fix:
	./php-cs-fixer fix --using-cache=no --rules=@PSR12 --path-mode=override yii/src

fix-file:
	@if [ -z "$(FILE)" ]; then \
		echo "Usage: make fix-file FILE=path/to/file.php" >&2; \
		exit 1; \
	fi
	./php-cs-fixer fix --using-cache=no --rules=@PSR12 --path-mode=override "$(FILE)"

fix-changed:
	@FILES="$$( (git diff --name-only --diff-filter=ACMR -- '*.php'; git diff --name-only --diff-filter=ACMR --cached -- '*.php') | sort -u )"; \
	if [ -z "$$FILES" ]; then \
		echo "No changed PHP files."; \
		exit 0; \
	fi; \
	./php-cs-fixer fix --using-cache=no --rules=@PSR12 --path-mode=override $$FILES

migrate-test:
	docker exec -e YII_ENV=test aimm_yii ./yii migrate

docs-install:
	npm run docs:install

docs-dev:
	npm run docs:dev

docs-build:
	npm run docs:build

docs-preview:
	npm run docs:preview
