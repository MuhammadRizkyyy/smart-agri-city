
.PHONY: help up down restart ps logs stats clean k8s-apply k8s-delete

# Default command lists all recipes
help:
	@echo "=============================================================================="
	@echo "                  SMART AGRI CITY INTEGRATED PLATFORM COMMANDS"
	@echo "=============================================================================="
	@echo "Docker Compose Commands:"
	@echo "  make up          - Build and run all services in the background"
	@echo "  make down        - Tear down all running containers, networks and volumes"
	@echo "  make restart     - Restart all containers"
	@echo "  make ps          - List status of all active containers"
	@echo "  make logs        - Follow logs from all active containers"
	@echo "  make stats       - Monitor container CPU and memory resources"
	@echo ""
	@echo "Kubernetes (kubectl) Commands:"
	@echo "  make k8s-apply   - Apply all Kubernetes manifests in /k8s"
	@echo "  make k8s-delete  - Delete all Kubernetes manifests in /k8s"
	@echo ""
	@echo "Maintenance Commands:"
	@echo "  make clean       - Clean temporary cache files (Python cache, logs, etc.)"
	@echo "=============================================================================="

# DOCKER COMPOSE SHORTCUTS 
up:
	docker compose up -d --build

down:
	docker compose down -v

restart:
	docker compose restart

ps:
	docker compose ps

logs:
	docker compose logs -f

stats:
	docker stats

# KUBERNETES MANIFEST SHORTCUTS 
k8s-apply:
	kubectl apply -f k8s/namespace.yaml
	kubectl apply -f k8s/ -n agriCity

k8s-delete:
	kubectl delete -f k8s/ -n agriCity
	kubectl delete -f k8s/namespace.yaml

# CLEAN UP UTILITIES
clean:
	@echo "Cleaning up local build and cache artifacts..."
	# Remove Node.js dependencies
	# find . -name "node_modules" -type d -prune -exec rm -rf '{}' +
	# Remove Composer vendors
	# find . -name "vendor" -type d -prune -exec rm -rf '{}' +
	# Remove Python cache
	find . -name "__pycache__" -type d -exec rm -rf {} +
	find . -name "*.pyc" -delete
	find . -name ".pytest_cache" -type d -exec rm -rf {} +
	@echo "Cleanup completed successfully!"
