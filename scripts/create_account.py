#!/usr/bin/env python3
"""Create/update/delete RoomRSV account data in Redis."""

import argparse
import re
import subprocess
import sys


ACCOUNT_PATTERN = re.compile(r"^[A-Za-z0-9_-]{1,64}$")


def redis_cli(*args):
    """Run a redis-cli command and return stdout."""
    cmd = ["redis-cli", *args]
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode != 0:
        stderr = result.stderr.strip() or "redis-cli failed"
        raise RuntimeError(stderr)
    return result.stdout.strip()


def account_exists(account_id):
    return redis_cli("EXISTS", f"account:{account_id}") == "1"


def create_or_update_account(account_id, password, tenant_id, update_if_exists=False):
    exists = account_exists(account_id)
    if exists and not update_if_exists:
        raise RuntimeError(
            f"account '{account_id}' already exists. Use --update-if-exists to overwrite."
        )

    redis_cli("HSET", f"account:{account_id}", "account_id", account_id)
    redis_cli("HSET", f"account:{account_id}", "tenant_id", str(tenant_id))
    redis_cli("HSET", f"account:{account_id}", "password", password)
    redis_cli("SADD", "accounts:all", account_id)

    if exists:
        print(f"Updated Redis account: {account_id} (tenant_id={tenant_id})")
    else:
        print(f"Created Redis account: {account_id} (tenant_id={tenant_id})")


def delete_account(account_id):
    removed_key = redis_cli("DEL", f"account:{account_id}")
    redis_cli("SREM", "accounts:all", account_id)
    if removed_key == "1":
        print(f"Deleted Redis account: {account_id}")
    else:
        print(f"Account not found, removed from set if present: {account_id}")


def parse_args():
    parser = argparse.ArgumentParser(
        description="Manage RoomRSV account data in Redis"
    )
    parser.add_argument("account", help="Account id, e.g. test01")
    parser.add_argument("password", nargs="?", default="", help="Account password")
    parser.add_argument(
        "--tenant-id",
        type=int,
        default=1,
        help="Tenant ID to bind account (default: 1)",
    )
    parser.add_argument(
        "--update-if-exists",
        action="store_true",
        help="Update existing account instead of failing",
    )
    parser.add_argument(
        "--delete",
        action="store_true",
        help="Delete account from Redis (ignore password arg)",
    )
    return parser.parse_args()


def main():
    args = parse_args()
    account_id = args.account.strip()

    if not ACCOUNT_PATTERN.match(account_id):
        print("Error: invalid account format.", file=sys.stderr)
        return 1

    if args.tenant_id <= 0:
        print("Error: tenant-id must be greater than 0.", file=sys.stderr)
        return 1

    try:
        redis_cli("PING")
    except RuntimeError as err:
        print(f"Error: cannot connect Redis: {err}", file=sys.stderr)
        return 1

    try:
        if args.delete:
            delete_account(account_id)
            return 0

        if args.password == "":
            print("Error: password is required unless --delete is used.", file=sys.stderr)
            return 1

        create_or_update_account(
            account_id=account_id,
            password=args.password,
            tenant_id=args.tenant_id,
            update_if_exists=args.update_if_exists,
        )
        return 0
    except RuntimeError as err:
        print(f"Error: {err}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
