#!/bin/bash

# Define colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        echo -e "${YELLOW}Docker is not running or you don't have permissions.${NC}"
        echo "Trying with sudo..."
        SUDO="sudo"
    else
        SUDO=""
    fi
}

# Show usage
show_usage() {
    echo -e "${BLUE}SEMA PHP Docker Manager${NC}"
    echo ""
    echo "Usage: ./run.sh [command]"
    echo ""
    echo "Commands:"
    echo "  start    - Start all containers (default)"
    echo "  stop     - Stop all containers"
    echo "  restart  - Restart all containers"
    echo "  logs     - Show logs from all containers"
    echo "  status   - Show container status"
    echo "  clean    - Stop and remove all containers and volumes"
    echo ""
}

# Start containers
start_containers() {
    check_docker
    echo -e "${GREEN}Starting SEMA PHP Docker Environment...${NC}"
    echo "Building and starting containers..."
    $SUDO docker-compose up -d --build

    # Install dependencies if vendor folder doesn't exist
    if [ ! -d "vendor" ]; then
        echo "Installing PHP dependencies..."
        $SUDO docker-compose run --rm web composer install
    fi

    echo -e "${GREEN}Environment is ready!${NC}"
    echo -e "Access the application at: ${GREEN}http://localhost:8080/sema-php/${NC}"
    echo -e "phpMyAdmin is available at: ${GREEN}http://localhost:8081${NC}"
}

# Stop containers
stop_containers() {
    check_docker
    echo -e "${YELLOW}Stopping containers...${NC}"
    $SUDO docker-compose down
    echo -e "${GREEN}Containers stopped!${NC}"
}

# Restart containers
restart_containers() {
    check_docker
    echo -e "${YELLOW}Restarting containers...${NC}"
    $SUDO docker-compose restart
    echo -e "${GREEN}Containers restarted!${NC}"
}

# Show logs
show_logs() {
    check_docker
    echo -e "${BLUE}Showing logs (Ctrl+C to exit)...${NC}"
    $SUDO docker-compose logs -f
}

# Show status
show_status() {
    check_docker
    echo -e "${BLUE}Container Status:${NC}"
    $SUDO docker-compose ps
}

# Clean everything
clean_all() {
    check_docker
    echo -e "${RED}This will stop and remove all containers and volumes!${NC}"
    read -p "Are you sure? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Cleaning up...${NC}"
        $SUDO docker-compose down -v
        echo -e "${GREEN}Cleanup complete!${NC}"
    else
        echo "Cancelled."
    fi
}

# Main script
case "${1:-start}" in
    start)
        start_containers
        ;;
    stop)
        stop_containers
        ;;
    restart)
        restart_containers
        ;;
    logs)
        show_logs
        ;;
    status)
        show_status
        ;;
    clean)
        clean_all
        ;;
    help|--help|-h)
        show_usage
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        show_usage
        exit 1
        ;;
esac
