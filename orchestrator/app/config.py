from functools import lru_cache
from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    openai_api_key: str | None = None
    github_token: str | None = None
    openai_model: str | None = None
    github_api_url: str = "https://api.github.com"
    project_registry_path: Path = Path("data/projects.json")
    orchestrator_db_path: Path = Path("data/orchestrator.db")
    port: int = 8421

    model_config = SettingsConfigDict(
        env_file=".env.local",
        env_file_encoding="utf-8",
        extra="ignore",
    )


@lru_cache
def get_settings() -> Settings:
    return Settings()
