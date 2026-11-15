output "app_public_ip" {
  description = "IP público da aplicação"
  value       = aws_instance.app_server.public_ip
}

output "db_private_ip" {
  description = "IP privado do PostgreSQL"
  value       = aws_instance.postgres_db.private_ip
}

output "postgres_private_ip" {
  description = "Endereço para DB_HOST"
  value       = aws_instance.postgres_db.private_ip
}

output "db_instance_id" {
  description = "ID da EC2 PostgreSQL para comandos SSM"
  value       = aws_instance.postgres_db.id
}

output "db_artifacts_bucket" {
  description = "Bucket S3 para os arquivos de instalação e restauração"
  value       = aws_s3_bucket.db_artifacts.bucket
}
output "nfs_private_ip" {
  description = "IP privado do servidor NFS (NFS_IP do observability/.env e PVs do k8s)"
  value       = aws_instance.nfs_server.private_ip
}

output "nfs_instance_id" {
  description = "ID da EC2 NFS para comandos SSM"
  value       = aws_instance.nfs_server.id
}
