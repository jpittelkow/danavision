#!/bin/bash
#
# DanaVision Version Bump Script
#
# Usage:
#   ./scripts/bump-version.sh [major|minor|patch] [--tag] [--push]
#   ./scripts/bump-version.sh --set 2.0.0 [--tag] [--push]
#
# Examples:
#   ./scripts/bump-version.sh patch           # 1.0.0 -> 1.0.1
#   ./scripts/bump-version.sh minor           # 1.0.0 -> 1.1.0
#   ./scripts/bump-version.sh major           # 1.0.0 -> 2.0.0
#   ./scripts/bump-version.sh --set 2.0.0     # Set specific version
#   ./scripts/bump-version.sh patch --tag     # Bump and create git tag
#   ./scripts/bump-version.sh patch --tag --push  # Bump, tag, and push
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
VERSION_FILE="$ROOT_DIR/VERSION"
PACKAGE_JSON="$ROOT_DIR/backend/package.json"
COMPOSER_JSON="$ROOT_DIR/backend/composer.json"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored message
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Get current version from VERSION file
get_current_version() {
    if [[ -f "$VERSION_FILE" ]]; then
        cat "$VERSION_FILE" | tr -d '[:space:]'
    else
        echo "0.0.0"
    fi
}

# Parse semantic version into components
parse_version() {
    local version="$1"
    # Remove 'v' prefix if present
    version="${version#v}"
    
    # Split by dots and extract major.minor.patch
    IFS='.' read -r MAJOR MINOR PATCH <<< "${version%%-*}"
    
    # Handle pre-release suffix
    PRERELEASE=""
    if [[ "$version" == *"-"* ]]; then
        PRERELEASE="${version#*-}"
    fi
}

# Bump version based on type
bump_version() {
    local current="$1"
    local bump_type="$2"
    
    parse_version "$current"
    
    case "$bump_type" in
        major)
            MAJOR=$((MAJOR + 1))
            MINOR=0
            PATCH=0
            ;;
        minor)
            MINOR=$((MINOR + 1))
            PATCH=0
            ;;
        patch)
            PATCH=$((PATCH + 1))
            ;;
        *)
            print_error "Unknown bump type: $bump_type"
            exit 1
            ;;
    esac
    
    echo "$MAJOR.$MINOR.$PATCH"
}

# Update VERSION file
update_version_file() {
    local new_version="$1"
    echo "$new_version" > "$VERSION_FILE"
    print_info "Updated VERSION file to $new_version"
}

# Update package.json version
update_package_json() {
    local new_version="$1"
    if [[ -f "$PACKAGE_JSON" ]]; then
        # Use sed to update version in package.json
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s/\"version\": \"[^\"]*\"/\"version\": \"$new_version\"/" "$PACKAGE_JSON"
        else
            sed -i "s/\"version\": \"[^\"]*\"/\"version\": \"$new_version\"/" "$PACKAGE_JSON"
        fi
        print_info "Updated package.json to $new_version"
    else
        print_warning "package.json not found at $PACKAGE_JSON"
    fi
}

# Update composer.json version
update_composer_json() {
    local new_version="$1"
    if [[ -f "$COMPOSER_JSON" ]]; then
        # Use sed to update version in composer.json
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s/\"version\": \"[^\"]*\"/\"version\": \"$new_version\"/" "$COMPOSER_JSON"
        else
            sed -i "s/\"version\": \"[^\"]*\"/\"version\": \"$new_version\"/" "$COMPOSER_JSON"
        fi
        print_info "Updated composer.json to $new_version"
    else
        print_warning "composer.json not found at $COMPOSER_JSON"
    fi
}

# Create git tag
create_git_tag() {
    local version="$1"
    local tag="v$version"
    
    print_info "Creating git tag: $tag"
    git tag -a "$tag" -m "Release $tag"
    print_success "Created git tag: $tag"
}

# Push git tag
push_git_tag() {
    local version="$1"
    local tag="v$version"
    
    print_info "Pushing git tag: $tag"
    git push origin "$tag"
    print_success "Pushed git tag: $tag"
}

# Show usage
show_usage() {
    echo "DanaVision Version Bump Script"
    echo ""
    echo "Usage:"
    echo "  $0 [major|minor|patch] [--tag] [--push]"
    echo "  $0 --set VERSION [--tag] [--push]"
    echo ""
    echo "Options:"
    echo "  major       Bump major version (1.0.0 -> 2.0.0)"
    echo "  minor       Bump minor version (1.0.0 -> 1.1.0)"
    echo "  patch       Bump patch version (1.0.0 -> 1.0.1)"
    echo "  --set VER   Set specific version"
    echo "  --tag       Create a git tag for the new version"
    echo "  --push      Push the git tag to origin (requires --tag)"
    echo "  --help      Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 patch                    # 1.0.0 -> 1.0.1"
    echo "  $0 minor --tag              # Bump and tag"
    echo "  $0 --set 2.0.0-beta.1       # Set pre-release version"
    echo ""
    echo "Current version: $(get_current_version)"
}

# Main script
main() {
    local bump_type=""
    local set_version=""
    local create_tag=false
    local push_tag=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            major|minor|patch)
                bump_type="$1"
                shift
                ;;
            --set)
                set_version="$2"
                shift 2
                ;;
            --tag)
                create_tag=true
                shift
                ;;
            --push)
                push_tag=true
                shift
                ;;
            --help|-h)
                show_usage
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Validate arguments
    if [[ -z "$bump_type" && -z "$set_version" ]]; then
        print_error "No version bump type or --set version specified"
        show_usage
        exit 1
    fi
    
    if [[ -n "$bump_type" && -n "$set_version" ]]; then
        print_error "Cannot use both bump type and --set"
        exit 1
    fi
    
    if [[ "$push_tag" == true && "$create_tag" != true ]]; then
        print_error "--push requires --tag"
        exit 1
    fi
    
    # Get current version
    local current_version
    current_version=$(get_current_version)
    print_info "Current version: $current_version"
    
    # Determine new version
    local new_version
    if [[ -n "$set_version" ]]; then
        new_version="${set_version#v}"  # Remove 'v' prefix if present
    else
        new_version=$(bump_version "$current_version" "$bump_type")
    fi
    
    print_info "New version: $new_version"
    
    # Confirm
    echo ""
    read -p "Proceed with version bump? (y/N) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warning "Aborted"
        exit 0
    fi
    
    # Update files
    echo ""
    update_version_file "$new_version"
    update_package_json "$new_version"
    update_composer_json "$new_version"
    
    # Create git tag if requested
    if [[ "$create_tag" == true ]]; then
        echo ""
        create_git_tag "$new_version"
        
        if [[ "$push_tag" == true ]]; then
            push_git_tag "$new_version"
        fi
    fi
    
    echo ""
    print_success "Version bumped to $new_version"
    
    if [[ "$create_tag" != true ]]; then
        echo ""
        print_info "To create a git tag, run:"
        echo "  git add VERSION backend/package.json backend/composer.json"
        echo "  git commit -m \"Bump version to $new_version\""
        echo "  git tag -a v$new_version -m \"Release v$new_version\""
        echo "  git push origin v$new_version"
    fi
}

main "$@"
